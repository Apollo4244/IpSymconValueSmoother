# Value Smoothing
Smoothes variable values using Exponential Moving Average (EMA). Automatically creates and maintains smoothed copies of selected source variables.

### Table of Contents

1. [Scope of Functions](#1-scope-of-functions)
2. [Requirements](#2-requirements)
3. [Software Installation](#3-software-installation)
4. [Setting Up Instances in Symcon](#4-setting-up-instances-in-symcon)
5. [Status Variables and Profiles](#5-status-variables-and-profiles)
6. [WebFront](#6-webfront)
7. [PHP Command Reference](#7-php-command-reference)

### 1. Scope of Functions

*

### 2. Requirements

- Symcon version 7.1 or higher

### 3. Software Installation

* Install the 'Value Smoothing' module via the Module Store.
* Alternatively, add the following URL via Module Control

### 4. Setting Up Instances in Symcon

Under 'Add Instance', the 'Value Smoothing' module can be found using the quick filter.  
	- Further information on adding instances in the [Instances documentation](https://www.symcon.de/service/dokumentation/konzepte/instanzen/#Instanz_hinzufügen)

__Configuration page__:

Name     | Description
-------- | ------------------
         |
         |

### 5. Status Variables and Profiles

Status variables are created automatically. Deleting individual ones may cause malfunctions.

#### Status Variables

Name   | Type    | Description
------ | ------- | ------------
       |         |
       |         |

#### Profiles

Name   | Typ
------ | -------
       |
       |

### 6. Visualisierung

Die Funktionalität, die das Modul in der Visualisierung bietet.

### 7. PHP-Befehlsreferenz

`boolean VALUESMOOTHING_BeispielFunktion(integer $InstanzID);`
Erklärung der Funktion.

Beispiel:
`VALUESMOOTHING_BeispielFunktion(12345);`