<?php

declare(strict_types=1);

/**
 * ValueSmoothing
 *
 * Smooths variable values using Exponential Moving Average (EMA).
 * Automatically creates and maintains smoothed child variables for each
 * configured source variable. The EMA time constant τ is configurable
 * per variable. An internal decay timer ensures the EMA converges to zero
 * even when the source variable stops firing update events.
 */
class ValueSmoothing extends IPSModule
{
    private const DECAY_INTERVAL_DEFAULT = 5000; // Default decay check interval: 5 s
    private const TAU_MIN                = 10;   // Minimum τ in seconds
    private const TAU_MAX                = 300;  // Maximum τ in seconds

    public function Create(): void
    {
        parent::Create();

        $this->RegisterPropertyString('Variables', '[]');
        $this->RegisterPropertyInteger('DecayInterval', self::DECAY_INTERVAL_DEFAULT);
        $this->RegisterAttributeString('RegisteredVarIds', '[]');
        $this->RegisterAttributeString('LastTimestamps', '{}');
        $this->RegisterAttributeString('EMAValues', '{}');

        $this->RegisterTimer('DecayTimer', 0, 'VALUESMOOTHING_DecayTick(' . $this->InstanceID . ');');
    }

    public function Destroy(): void
    {
        parent::Destroy();
    }

    public function ApplyChanges(): void
    {
        parent::ApplyChanges();

        // Unregister all previously tracked VM_UPDATE messages
        $prevIds = json_decode($this->ReadAttributeString('RegisteredVarIds'), true) ?? [];
        foreach ($prevIds as $varId) {
            $this->UnregisterMessage((int) $varId, VM_UPDATE);
        }

        $variables = json_decode($this->ReadPropertyString('Variables'), true) ?? [];
        $newIds    = [];
        $position  = 1;

        foreach ($variables as $entry) {
            $sourceVarId = (int) ($entry['SourceVarId'] ?? 0);
            if ($sourceVarId === 0 || !IPS_VariableExists($sourceVarId)) {
                continue;
            }

            $varInfo = IPS_GetVariable($sourceVarId);
            $varType = $varInfo['VariableType'];

            // EMA is only meaningful for numeric types
            if ($varType !== VARIABLETYPE_FLOAT && $varType !== VARIABLETYPE_INTEGER) {
                continue;
            }

            $name  = IPS_GetName($sourceVarId);
            $ident = 'EMA_' . $sourceVarId;

            // Determine presentation mode: new-style (>= 8.0) or legacy profile
            $customPresentation = IPS_GetVariablePresentation($sourceVarId);
            if (!empty($customPresentation)) {
                // New-style presentation — MaintainVariable needs no profile, apply afterwards
                $this->MaintainVariable($ident, $name, $varType, '', $position, true);
                $emaVarId = $this->GetIDForIdent($ident);
                IPS_SetVariableCustomPresentation($emaVarId, $customPresentation);
            } else {
                // Legacy profile-based presentation
                $profile = $varInfo['VariableCustomProfile'] ?: $varInfo['VariableProfile'] ?: '';
                $this->MaintainVariable($ident, $name, $varType, $profile, $position, true);
            }
            $this->RegisterMessage($sourceVarId, VM_UPDATE);

            $newIds[] = $sourceVarId;
            $position++;
        }

        // Delete EMA variables, timestamps and stored EMA values that are no longer in the configuration
        $timestamps = json_decode($this->ReadAttributeString('LastTimestamps'), true) ?? [];
        $emaValues  = json_decode($this->ReadAttributeString('EMAValues'), true) ?? [];
        foreach ($prevIds as $varId) {
            if (!in_array((int) $varId, $newIds, true)) {
                $this->MaintainVariable('EMA_' . $varId, '', VARIABLETYPE_FLOAT, '', 0, false);
                unset($timestamps[(string) $varId]);
                unset($emaValues[(string) $varId]);
            }
        }
        $this->WriteAttributeString('LastTimestamps', json_encode($timestamps));
        $this->WriteAttributeString('EMAValues', json_encode($emaValues));

        $this->WriteAttributeString('RegisteredVarIds', json_encode($newIds));

        $decayIntervalMs = max(1000, min(10000, $this->ReadPropertyInteger('DecayInterval')));
        if (count($newIds) === 0) {
            $this->SetTimerInterval('DecayTimer', 0);
            $this->SetStatus(104); // Inactive: no variables configured
        } else {
            $this->SetTimerInterval('DecayTimer', $decayIntervalMs);
            $this->SetStatus(102); // Active
        }
    }

    public function MessageSink($timestamp, $senderID, $message, $data): void
    {
        if ($message === VM_UPDATE) {
            $this->ProcessEMA((int) $senderID, false);
        }
    }

    /**
     * Called by the internal decay timer (every DECAY_TIMER_MS).
     * Drives EMA decay for source variables that have stopped firing updates.
     */
    public function DecayTick(): void
    {
        $variables = json_decode($this->ReadPropertyString('Variables'), true) ?? [];
        foreach ($variables as $entry) {
            $sourceVarId = (int) ($entry['SourceVarId'] ?? 0);
            if ($sourceVarId > 0 && IPS_VariableExists($sourceVarId)) {
                $this->ProcessEMA($sourceVarId, true);
            }
        }
    }

    // -------------------------------------------------------------------------
    // Private helpers
    // -------------------------------------------------------------------------

    private function ProcessEMA(int $sourceVarId, bool $isDecay): void
    {
        // Locate the configuration entry for this source variable
        $variables = json_decode($this->ReadPropertyString('Variables'), true) ?? [];
        $entry     = null;
        foreach ($variables as $e) {
            if ((int) ($e['SourceVarId'] ?? 0) === $sourceVarId) {
                $entry = $e;
                break;
            }
        }
        if ($entry === null) {
            return;
        }

        $tau                = (float) max(self::TAU_MIN, min(self::TAU_MAX, (float) ($entry['Tau'] ?? 30)));
        $rangeFilterEnabled = (bool) ($entry['RangeFilterEnabled'] ?? false);
        $rangeMin           = (float) ($entry['RangeMin'] ?? 0.0);
        $rangeMax           = (float) ($entry['RangeMax'] ?? 0.0);

        $emaVarId = @$this->GetIDForIdent('EMA_' . $sourceVarId);
        if (!$emaVarId) {
            return;
        }

        // Read full-precision EMA state from internal attribute (not from the rounded display variable)
        $emaValues = json_decode($this->ReadAttributeString('EMAValues'), true) ?? [];
        $emaAlt    = (float) ($emaValues[(string) $sourceVarId] ?? 0.0);

        // Use per-variable microtime timestamps for sub-second precision
        $timestamps = json_decode($this->ReadAttributeString('LastTimestamps'), true) ?? [];
        $tAlt       = (float) ($timestamps[(string) $sourceVarId] ?? 0.0);
        $tNow       = microtime(true);
        $deltaT     = ($tAlt > 0.0) ? max(0.001, $tNow - $tAlt) : 0.0;

        // Decay tick: skip if the EMA was processed very recently — MessageSink handles active sources.
        // Guard is based on the configured decay interval to avoid double-processing, not on τ.
        $decayIntervalS = max(1000, min(10000, $this->ReadPropertyInteger('DecayInterval'))) / 1000;
        if ($isDecay && $tAlt > 0.0 && $deltaT < ($decayIntervalS * 0.9)) {
            return;
        }

        $rawValue = (float) GetValue($sourceVarId);

        // Range filter: skip values outside the configured range entirely (e.g. Modbus read errors)
        if ($rangeFilterEnabled && ($rawValue < $rangeMin || $rawValue > $rangeMax)) {
            return;
        }

        $messwert = $rawValue;

        // Cold start (tAlt = 0.0): jump directly to the first measurement
        $alpha  = ($tAlt === 0.0) ? 1.0 : 1.0 - exp(-$deltaT / $tau);
        $emaNeu = $alpha * $messwert + (1.0 - $alpha) * $emaAlt;

        $varType = IPS_GetVariable($sourceVarId)['VariableType'];
        if ($varType === VARIABLETYPE_INTEGER) {
            SetValueInteger($emaVarId, (int) round($emaNeu));
        } else {
            SetValueFloat($emaVarId, round($emaNeu, 1));
        }

        // Persist full-precision EMA value and microtime timestamp for next call
        $emaValues[(string) $sourceVarId]  = $emaNeu;
        $timestamps[(string) $sourceVarId] = $tNow;
        $this->WriteAttributeString('EMAValues', json_encode($emaValues));
        $this->WriteAttributeString('LastTimestamps', json_encode($timestamps));
    }
}