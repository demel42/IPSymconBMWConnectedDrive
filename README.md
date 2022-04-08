# IPSymconBMWConnectedDrive
[![Version](https://img.shields.io/badge/Symcon-PHPModul-red.svg)](https://www.symcon.de/service/dokumentation/entwicklerbereich/sdk-tools/sdk-php/)
[![Version](https://img.shields.io/badge/Symcon%20Version-%3E%205.1-green.svg)](https://www.symcon.de/service/dokumentation/installation/)
![Code](https://img.shields.io/badge/Code-PHP-blue.svg)
[![StyleCI](https://github.styleci.io/repos/118332358/shield?branch=master)](https://github.styleci.io/repos/118332358)

Modul für IP-Symcon ab Version 5. Ermöglicht die Kommunikation mit BMW Connected Drive.

## Dokumentation

**Inhaltsverzeichnis**

1. [Funktionsumfang](#1-funktionsumfang)
2. [Voraussetzungen](#2-voraussetzungen)
3. [Installation](#3-installation)
4. [Funktionsreferenz](#4-funktionsreferenz)
5. [Konfiguration](#5-konfiguartion)
6. [Anhang](#6-anhang)
7. [Versions-Historie](#7-versions-historie)

## 1. Funktionsumfang

Mit dem Modul lassen sich Befehle an einen BMW mit BMW Connected Drive schicken und Statusmeldungen über BMW Connected Drive in IP-Symcon darstellen.

### Befehle an BMW Connected Drive senden:

 - Klimatisierung starten / stoppen
 - Türen entriegeln / verriegeln
 - Lichthupe auslösen
 - Hupe auslösen
 - Fahrzeug suchen
 - POI an Fahrzeug enden

### Status Rückmeldung:

 - Fahrzeugdaten
 - Fahrzeugposition in Karte
 - aktuelle Position (Breitengard, Längengrad) sowie Ausrichtung
 - Tankinhalt, Tankreichweite
 - Status Veriegelung Türen, Fenster etc
 - Kilometerstand
 - Service-Meldungen, Check-Control-Meldungen
 - RemoteService-Verlauf

## 2. Voraussetzungen

 - IPS 4.x
 - ein BMW mit BMW Connected Drive

## 3. Installation

### a. Laden des Moduls

Die Webconsole von IP-Symcon mit _http://\<IP-Symcon IP\>:3777/console/_ öffnen.

Den **Modulstore** öffnen und im Suchfeld nun `BMW Connected Drive` eingeben, das Modul auswählen und auf _Installieren_ auswählen.

Alternativ kann das Modul auch über **ModulControl** (im Objektbaum innerhalb _Kern Instanzen_ die Instanz _Modules_) installiert werden,
als URL muss `https://github.com/demel42/IPSymconBMWConnectedDrive` angegeben werden.

### b. Einrichtung in IP-Symcon

In IP-Symcon nun _Instanz hinzufügen_ (_Rechtsklick -> Objekt hinzufügen -> Instanz_) auswählen unter der Kategorie, unter der man die BMW Instanz hinzufügen will, und _BMW_ auswählen.
Im Konfigurationsformular ist der _BMW Connected Drive User_,  das _BMW Connected Drive Passwort_ sowie die _VIN_ (Fahrgestellnummer) zu ergänzen und das Modell einzustellen.

## 4. Funktionsreferenz

`BMW_SetUpdateIntervall(integer $InstanceID, int $Minutes)`<br>
Update-Intervall setzen. Ist der Wert von _Minutes_ gleich 0, wird der konfigurierte Wert verwendet.

`BMW_GetRawData(integer $InstanceID, string $Name)`<br>
Liefert die Daten, die mit den entsprecheden HTTP-Aufrufen gewonnen werden. Sind die gleichen Daten, die früher in den gleichnamigen Variablen abgelegt wurden.
Verfügbar sind z.Zt. *VehicleData*, *RemoteServiceHistory* sowie *ChargingStatistics*, *ChargingSessions*.

`BMW_StartClimateControl(integer $InstanceID)`<br>
Startet die Klimatisierung

`BMW_StopClimateControl(integer $InstanceID)`<br>
Stoppt eine laufende Klimatisierung

`BMW_LockDoors(integer $InstanceID)`<br>
Versperrt die Türen

`BMW_UnlockDoors(integer $InstanceID)`<br>
Entsperrt die Türen

`BMW_FlashHeadlights(integer $InstanceID)`<br>
Löst die Lichthupe aus

`BMW_BlowHorn(integer $InstanceID)`<br>
Löst die Hupe aus

`BMW_LocateVehicle(integer $InstanceID)`<br>
Startet die Suche nach dem Fahrzeug, die gelieferte Position wird dann in den Variablen gespeichert

`BMW_SendPOI(integer $InstanceID, string $poi)`<br>
Sendet ein Reiseziel an das Fahrzeug.<br>
Die Daten sind in der json-kodierten Variable *poi' enthalten.
Vorgeschrieben ind die Zielposition (*longitude*, *latitude*), optional sind *name* sowie *street*, *postalCode*, *city*, *country*.

```
$poi = [
    'longitude'  => 7.214935,
    'latitude'   => 51.482533,
    'name'       => 'Rathaus Bochum',
    'street'     => 'Willy-Brandt-Platz',
    'city'       => 'Bochum',
    'postalCode' => '44777'
];
BMW_SendPOI(<InstanceID>, json_encode($poi));
```

oder minimal

```
$poi = [
    'longitude' => 7.223049,
    'latitude'  => 51.479018,
];
BMW_SendPOI(<InstanceID>, json_encode($poi));
```

## 5. Konfiguration:

### Variablen

| Eigenschaft              | Typ     | Standardwert | Funktion |
| :----------------------- | :------ | :----------- | :------- |
| user                     | string  |              | Benutzerkennung |
| password                 | string  |              | Passwort |
| country                  | integer | 1            | Land |
| vin                      | string  |              | Fahrgestellnummer |
| model                    | integer | 1            | Modell (Elektisch, Hybrid, Verbrenner) |
| brand                    | integer | 1            | Marke (BMW, Mini) |
|                          |         |              | |
| active_climate           | boolean | false        | Klimatisierung auslösen |
| active_lock              | boolean | false        | Türverschluss auslösen |
| active_flash_headlights  | boolean | false        | Lichthupe auslösen |
| active_blow_horn         | boolean | false        | Hupe auslösen |
| active_vehicle_finder    | boolean | false        | Fahrzeugsuche auslösen |
|                          |         |              | |
| active_lock_data         | boolean | false        | Verschluss-Status anzeigen |
| active_motion            | boolean | false        | Fahrzeugbewegung anzeigen |
| active_service           | boolean | false        | Service-Meldungen anzeigen |
| active_checkcontrol      | boolean | false        | Check-Control-Meldungen anzeigen |
| active_current_position  | boolean | false        | aktuelle Position anzeigen |
|                          |         |              | |
| active_googlemap         | boolean | false        | Anzeige einer Karte mit der Fahrzeuposition |
| googlemap_api_key        | string  |              | GoogleMaps API-Key |
| horizontal_mapsize       | integer | 600          |  ... horizontale Größe |
| vertical_mapsize         | integer | 400          |  ... vertikale Größe |
|                          |         |              | |
| UpdateInterval           | integer | 10           | Update-Intervall (in Minuten) |

### Variablenprofile

Es werden folgende Variableprofile angelegt:
* Boolean<br>
BMW.YesNo

* Integer<br>
BMW.ChargingStatus,
BMW.ConnectorStatus,
BMW.DoorClosureState,
BMW.DoorState,
BMW.Googlemap,
BMW.Heading,
BMW.HoodState,
BMW.Mileage,
BMW.MoonroofState,
BMW.SunroofState,
BMW.TriggerRemoteService,
BMW.TrunkState,
BMW.WindowState

* Float<br>
BMW.BatteryCapacity,
BMW.ChargingLevel,
BMW.Location,
BMW.RemainingRange,
BMW.StateofCharge,
BMW.TankCapacity,
BMW.TirePressure

## 6. Anhang

GUIDs

- Modul: `{3BEBDFE5-9DAF-3373-BFA9-A80038D3FE63}`
- Instanzen:
  - BMWConnectedDrive: `{8FD2A163-E07A-A2A2-58CC-974155FAEE33}`

Quellen / Referenzen

- [Bimmer Connected](https://github.com/bimmerconnected/bimmer_connected)
- [BMW-i-Remote](https://github.com/edent/BMW-i-Remote "BMW-i-Remote")

## 7. Versions-Historie

- 2.2 @ 08.04.2022 18:33 (beta)
  - bei einem reinen Elektro-Modell weder Tankinhalt noch kombinierte Reichweite anzeigen
  - Reifendruck anzeigen
  - diverse Verbesserungen zu den Ladeinformationen

- 2.1 @ 31.03.2022 09:47
  - Absicherung in GetRemoteServiceHistory()
  - libs/CommonStubs aktualisiert

- 2.0.15 @ 21.03.2022 17:37
  - Aktionen hinzugefügt
  - Korrektur: Funktion 'SetUpdateIntervall' umbenannt in 'SetUpdateInterval'

- 2.0.14 @ 03.03.2022 15:56
  - Fix in CommonStubs
  - Möglichkeit der Anzeige der Instanz-Referenzen

- 2.0.13 @ 20.02.2022 18:19
  - libs/common.php -> CommonStubs

- 2.0.12 @ 17.02.2022 21:24
  - GetCarPicture() ist nun "public"

- 2.0.11 @ 17.01.2022 20:05 (beta)
  - function UpdateRemoteServiceStatus() ist wieder public

- 2.0.10 @ 14.01.2022 17:08
  - weitere API-Anpsssung: immer "user-agent" im HTTP-Header schicken
  - RemoteService-Call "CHARGE_NOW"
  - SendPOI: Reiseziel an Fahrzeug senden

- 2.0.9 @ 29.12.2021 16:12
  - optionale Ausgabe des Zustands eines Schiebedachs
    es werden in den Bereich des Verschlusses nun nur noch die Variablen angelegt, die die API auch schickt
  - Zugriff auf die Rohdaten der API-Calls via BMW_GetRawData(),
    Verfügbar ist: VehicleData, ChargingStatistics, ChargingSessions, RemoteServiceHistory

- 2.0.8 @ 26.12.2021 18:36
  - Ladezykluѕ: verbesserte Erkennung des Status sowie Ermittlung Begin / Ende
  - Absicherung fehlgeschlagener HTTP-Calls

- 2.0.7 @ 22.12.2021 19:09
  - Unterscheidung zwischen den Marken BMW und Mini

- 2.0.6 @ 22.12.2021 15:07
  - Debug-Ausgabe im Rahmen von SetUpdateIntervall()

- 2.0.5 @ 17.12.2021 15:32
  - Auswertung des Ergebnisses von RemoteService-Call "VEHICLE_FINDER"

- 2.0.4 @ 17.12.2021 12:08
  - Karten-Skalierung wieder von 0..100%
  - Bild des Fahrzeugs

- 2.0.3 @ 16.12.2021 15:09
  - + Tür-Verschlussstatus

- 2.0.2 @ 16.12.2021 14:06
  - diverse Fixes

- 2.0.1 @ 15.12.2021 21:26
  - komplette Umstellung der API

- 1.13 @ 11.11.2021 10:35
  - teilweise Umstellung der API
  - + battery_size_max in kWh
  - interne Umstellungen

  Achtung: vor Update Variablenprofil _BMW.BatteryCapacity_ und Variable _bmw_battery_size_ löschen

- 1.12 @ 22.04.2021 08:08
  - erneute Änderung der API, Command "navigation" funktioniert nicht mehr, daher kein "soc", "socMax"

- 1.11 @ 15.02.2021 14:10
  - die API hat sich geändert
  - Variablen für Zeitpunkt der letzten Statusmeldung und Status der letzten Fahrzeugsuche
  - SetValue() abgesichert

- 1.10 @ 03.02.2020 16:01
  - das bisherige Datenfeld 'socMax' heisst nun 'socmax'. Es werden beide Schreibweisen unterstützt

- 1.9 @ 18.01.2020 10:28
  - Fix wg. 'strict_types=1': json_decode() muss immer einen String übergeben bekommen

- 1.8 @ 12.06.2019 18:06
  - Tabelle "Verlauf" um "Channel" ergänzt, Tabelle "Service" angepasst

- 1.7 @ 10.06.2019 11:48
  - Schrebifehler korrigiert

- 1.6 @ 10.02.2019 11:09
  - Absicherung des Datenabrufs von GetRemoteServices()

- 1.5 @ 21.01.2019 18:13
  - Fix zu 1.4

- 1.4 @ 18.01.2019 18:20
  - Sicherheitsabfragen auf leere Strukturen, mehr Debug

- 1.3 @ 04.01.2019 14:50
  - für elektrisch/hybrid: Übernahme der Angaben zur letzten Fahrt, Gesamtfahrten und Effizienz

- 1.2 @ 13.10.2018 17:58
  - Umstellung der internen Speicherung zur Vermeidung der Warnung _Puffer > 8kb_.

- 1.1 @ 04.09.2018 09:36
  - Übernahme von soc/socMax (Ladekapazität) für e-Modelle
  - Angabe des GoogleMaps-API-Keys (ist seit 06/2018 erforderlich)
  - Versionshistorie dazu

- 1.0 @ 05.04.2017
  Initiale Version
