# Value Smoothing

Smooths variable values using Exponential Moving Average (EMA). Automatically creates and maintains smoothed child variables for each configured source variable.

### Table of Contents

1. [Scope of Functions](#1-scope-of-functions)
2. [Requirements](#2-requirements)
3. [Software Installation](#3-software-installation)
4. [Setting Up Instances in Symcon](#4-setting-up-instances-in-symcon)
5. [Status Variables](#5-status-variables)
6. [WebFront](#6-webfront)
7. [PHP Command Reference](#7-php-command-reference)

### 1. Scope of Functions

Creates smoothed (EMA-filtered) copies of selected source variables as child variables of the module instance. Each smoothed variable reacts immediately to source updates whilst suppressing short-term noise. An internal decay timer ensures the EMA continues converging even when the source variable stops firing update events (e.g. a constant Modbus value).

**Exponential Moving Average formula:**

$$\alpha = 1 - e^{-\Delta t \,/\, \tau} \qquad \text{EMA}_\text{new} = \alpha \cdot m + (1-\alpha) \cdot \text{EMA}_\text{old}$$

- $\tau$ — time constant in seconds (configurable per variable, 10–300 s)
- $\Delta t$ — elapsed time since the last update (sub-second precision via `microtime`)
- The larger $\tau$, the slower/smoother the response
- Cold start: first value is adopted directly ($\alpha = 1.0$)

**Optional range filter:** values outside a configured Min/Max range are discarded entirely (useful to suppress Modbus read errors that produce extreme outliers).

### 2. Requirements

- IP-Symcon 8.0 or higher

### 3. Software Installation

* Install the **EMA Value Smoother** library via the Module Store.
* Alternatively, add the following URL via Module Control:  
  `https://github.com/Apollo4244/IpSymconValueSmoother`

### 4. Setting Up Instances in Symcon

Under *Add Instance*, the **Value Smoothing** module is found using the quick filter.

- Further information on adding instances in the [Instances documentation](https://www.symcon.de/service/dokumentation/konzepte/instanzen/#Instanz_hinzufügen)

**Configuration page:**

| Name | Description |
|---|---|
| Variables | List of source variables to smooth. Each row creates one EMA child variable. |
| Source Variable | The source variable to smooth (Float or Integer only). |
| τ (s) | EMA time constant in seconds (10–300). Higher = smoother but slower response. |
| Range Filter | Enable to discard values outside Min/Max before feeding them into the EMA. |
| Min | Lower bound for the range filter. Values below this are ignored. |
| Max | Upper bound for the range filter. Values above this are ignored. |

After clicking *Apply*, the module automatically:
- Creates a smoothed child variable for each configured source variable
- Copies the name, type and presentation profile from the source variable
- Starts monitoring the source variable for updates via `VM_UPDATE`

### 5. Status Variables

EMA child variables are created automatically as children of the instance. They use the same name, type, and variable presentation profile as the corresponding source variable.

Manually deleting individual child variables will not cause errors but the smoothed value will no longer be updated until *Apply* is pressed again.

**Instance status codes:**

| Code | Meaning |
|---|---|
| 102 | Active — at least one variable is configured and being smoothed |
| 104 | Inactive — no variables configured |

### 6. WebFront

The smoothed child variables appear in the WebFront identically to normal variables. They can be linked, visualised in charts, or used in automations just like any other variable.

### 7. PHP Command Reference

There are no public PHP functions for this module. All configuration is done through the instance configuration page.