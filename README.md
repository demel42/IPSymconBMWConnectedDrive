# IPSymconBMWConnectedDrive

[![IPS-Version](https://img.shields.io/badge/Symcon_Version-6+-red.svg)](https://www.symcon.de/service/dokumentation/entwicklerbereich/sdk-tools/sdk-php/)
![Code](https://img.shields.io/badge/Code-PHP-blue.svg)
[![License](https://img.shields.io/badge/License-CC%20BY--NC--SA%204.0-green.svg)](https://creativecommons.org/licenses/by-nc-sa/4.0/)

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

 - IP-Symcon ab Version 6
 - ein BMW mit eingerichtetem BMW Connected Drive

## 3. Installation

### a. Laden des Moduls

Die Webconsole von IP-Symcon mit _http://\<IP-Symcon IP\>:3777/console/_ öffnen.

Den **Modulstore** öffnen und im Suchfeld nun `BMW Connected Drive` eingeben, das Modul auswählen und auf _Installieren_ auswählen.

Alternativ kann das Modul auch über **ModulControl** (im Objektbaum innerhalb _Kern Instanzen_ die Instanz _Modules_) installiert werden,
als URL muss `https://github.com/demel42/IPSymconBMWConnectedDrive` angegeben werden.

### b. Einrichtung in IP-Symcon

In IP-Symcon nun unterhalb von _I/O Instanzen_ die Funktion _Instanz hinzufügen_ auswählen und als Hersteller _BMW_ angeben und _BMW ConnectedDrive I/O_ auswählen.
Instanz parametrieren.
Dann unterhalb von _Konfigurator Instanzen_ ein _BMW ConnectedDrive Konfigurator_ angeleg und hierüber den/die _BMW ConnectedDrive Vehicle_ anlegen.

## 4. Funktionsreferenz

`BMW_SetUpdateIntervall(integer $InstanceID, int $Minutes)`<br>
ändert das Aktualisierumgsintervall; eine Angabe von **null** setzt auf den in der Konfiguration vorgegebene Wert zurück.
Es gibt hierzu auch zwei Aktionen (Setzen und Zurücksetzen).

`BMW_GetRawData(integer $InstanceID, string $Name)`<br>
Liefert die Daten, die mit den entsprecheden HTTP-Aufrufen gewonnen werden. Sind die gleichen Daten, die früher in den gleichnamigen Variablen abgelegt wurden.
Verfügbar sind z.Zt. *VehicleData*, *RemoteServiceHistory* sowie *ChargingSessions*.

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

`BMW_StartCharging(integer $InstanceID)`<br>
Startet den Ladevorgang

`BMW_StopCharging(integer $InstanceID)`<br>
Beendet den Ladevorgang

`BMW_SetChargingSettings(integer $InstanceID, int $TargetSoC, int $AcCurrentLimit)`<br>
Setze Lade-Einstellungen

## 5. Konfiguration:

### Variablen (BMWConnectedDriveIO)

| Eigenschaft              | Typ     | Standardwert | Funktion |
| :----------------------- | :------ | :----------- | :------- |
| user                     | string  |              | Benutzerkennung |
| password                 | string  |              | Passwort |
| country                  | integer | 1            | Land |
| brand                    | integer | 1            | Marke (BMW, Mini) |

### Variablen (BMWConnectedDriveVehicle)

| Eigenschaft              | Typ     | Standardwert | Funktion |
| :----------------------- | :------ | :----------- | :------- |
| vin                      | string  |              | Fahrgestellnummer |
| model                    | integer | 1            | Modell (Elektisch, Hybrid, Verbrenner) |
|                          |         |              | |
| active_climate           | boolean | false        | Klimatisierung auslösen |
| active_lock              | boolean | false        | Türverschluss auslösen |
| active_flash_headlights  | boolean | false        | Lichthupe auslösen |
| active_blow_horn         | boolean | false        | Hupe auslösen |
| active_vehicle_finder    | boolean | false        | Fahrzeugsuche auslösen |
|                          |         |              | |
| active_lock_data         | boolean | false        | Verschluss-Status anzeigen |
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
BMW.RoofState,
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
BMW.TankLevel,
BMW.TirePressure

## 6. Anhang

GUIDs

- Modul: `{3BEBDFE5-9DAF-3373-BFA9-A80038D3FE63}`
- Instanzen:
  - BMWConnectedDriveIO: `{2B3E3F00-33AC-4A54-8E20-F8B57241913D}`
  - BMWConnectedDriveConfig: `{BC548547-2497-00C5-1C33-33754EA50AE3}`
  - BMWConnectedDriveVehicle: `{8FD2A163-E07A-A2A2-58CC-974155FAEE33}`
- Nachrichten:
    - `{7D93F416-125A-4CAE-B707-0DB2A2361013}`: an BMWConnectedDriveConfig, BMWConnectedDriveDevice
    - `{67B1E7E9-97C7-43AC-BB2E-723FFE2444FF}`: an BMWConnectedDriveIO

Quellen / Referenzen

- [Bimmer Connected](https://github.com/bimmerconnected/bimmer_connected)

## 7. Versions-Historie

- 4.1 @ 23.01.2025 15:35
  - Fix: Auswertung der Klimatisierungs-Timer

- 4.0 @ 23.12.2024 11:11
  - Fix: Login mit Captcha
  - update submodule CommonStubs
  
- 3.11 @ 27.04.2024 16:19
  - Fix: API-Änderung für Lade-Historie nachgeführt
  - Änderung: Darstellung der Ladehistorie verbessert
  - update submodule CommonStubs

- 3.10 @ 15.02.2024 09:01
  - Neu: nach dem Setzen der Ladepräferenzen wird der "RemoteServiceStatus" abgefragt, bis das Kommando quittiert wurde
  - Verbesserung: wenn keine anhängenden RemoteServices mehr vorliegen, werden die Fahrzeugdaten aktualisiert

- 3.9 @ 07.02.2024 17:34
  - Fix: Absicherung von Zugriffen auf andere Instanzen in Konfiguratoren

- 3.8.1 @ 05.02.2024 16:23
  - Fix: Problem mit Verbrennern bei Version 3.8

- 3.8 @ 29.01.2024 10:14
  - Änderung: Medien-Objekte haben zur eindeutigen Identifizierung jetzt ebenfalls ein Ident
  - update submodule CommonStubs

- 3.7 @ 24.01.2024 08:10
  - Fix: Versuche, den http-error 403 "Out of call volume quota." abzufangen.
  - Fix: neue Version der BMW-API
  - Neu: Schalter, um Daten zu API-Aufrufen zu sammeln
    Die API-Aufruf-Daten stehen nun in einem Medienobjekt zur Verfügung
  - submodule CommonStubs aktualisiert

- 3.6 @ 14.12.2023 11:11
  - Fix: neue Version der BMW-API

- 3.5 @ 09.12.2023 17:19
  - Neu: ab IPS-Version 7 ist im Konfigurator die Angabe einer Import-Kategorie integriert, daher entfällt die bisher vorhandene separate Einstellmöglichkeit

- 3.4 @ 03.11.2023 11:06
  - Neu: Ermittlung von Speicherbedarf und Laufzeit (aktuell und für 31 Tage) und Anzeige im Panel "Information"
  - Fix: die Statistik der ApiCalls wird nicht mehr nach uri sondern nur noch host+cmd differenziert
  - update submodule CommonStubs

- 3.3.1 @ 04.08.2023 17:04
  - Fix: "Ladestrom-Begrenzung" wird in Ampere angegeben, nicht in %
  - Fix: "Lademodus" korrigiert

- 3.3 @ 26.07.2023 11:14 
  - Neu: Start/Stop des Ladevorgangs, Setzen von Ladeziel/Stromstärke

- 3.2 @ 06.07.2023 09:41
  - Vorbereitung auf IPS 7 / PHP 8.2
  - Neu: Schalter, um die Meldung eines inaktiven Gateway zu steuern
  - update submodule CommonStubs
    - Absicherung bei Zugriff auf Objekte und Inhalte

- 3.1.2 @ 05.02.2023 10:05
 - Fix: URL zur Dokumentation war falsch

- 3.1.1 @ 11.01.2023 15:38
  - Fix: Handling des Datencache abgesichert
  - update submodule CommonStubs

- 3.1 @ 06.01.2023 11:22
  - Neu: API aktualisiert (MyBMW 2.12.0)
    Erhofft wird ein verringertes Auftreten von HTTP-Error 403(Forbidden)/Quota Exceeded

- 3.0.4 @ 05.01.2023 08:27
  - Fix: Bilder vom Fahrzeug gibt es nun wieder in 3 Ansichten: von schräg vorne, von vorne, von der Seite

- 3.0.3 @ 02.01.2023 13:55
  - Fix: ServiceMessages ergänzt

- 3.0.2 @ 02.01.2023 11:09
  - Fix: GetCarPicture funktioniert wieder, 
    Es wird nur noch die Ansicht "Schräg von vorne" angeboten

- 3.0.1 @ 02.01.2023 09:41
  - Fix: RemoteServices konnten nicht mehr aufgerufen werden

- 3.0 @ 21.12.2022 09:55
  - Neu: Kommunikation über ein I/O-Modul (wird beim Update angelegt)
  - Neu: Konfigurator
  - Neu: Führen einer Statistik der API-Calls, Anzeige als Popup im Experten-Bereich
  - Neu: zur Vermeidung von Problemen wird nur noch ein API-Call/Sekunde ausgeführt
  - update submodule CommonStubs

- 2.11 @ 19.10.2022 09:31
  - Fix: MessageSink() angepasst, um Warnungen aufgrund zu langer Laufzeit von KR_READY zu vermeiden
  - update submodule CommonStubs

- 2.10.1 @ 07.10.2022 13:59
  - update submodule CommonStubs
    Fix: Update-Prüfung wieder funktionsfähig

- 2.10 @ 01.08.2022 17:18
  - Fix: manche Modelle liefern nun Tankfüllstand ('FuelPercent') statt Tankinhalt ('FuelLiters')

- 2.9.2 @ 26.07.2022 15:38
  - Fix: eine fehlende Übersetzung nachgetragen

- 2.9.1 @ 26.07.2022 10:17
  - Fix: Setzen von 'ChargingStart' und 'ChargingEnd' korrigiert
  - Fix: Aufbereitung für 'ChargingStatus' verfeinert
    Wenn Ladekabel-Status 'nicht verbunden' ist, dann ist der Ladezyklus-Status 'inaktiv'

- 2.9 @ 22.07.2022 15:46
  - Fix: "CheckControl" abgesichert, Übersetzung

- 2.8 @ 22.07.2022 09:31
  - Fix: Angabe der Kompatibilität auf 6.1 korrigiert

- 2.7 @ 21.07.2022 15:32
  - Fix: API-Änderung ('/v1/vehicles' ersetzt durch '/v2/vehicles')
    - es gibt keine Unterscheidung mehr von Schiebedach-Typen
	- Variable 'in Bewegung' ist weggefallen
	- Variable 'CheckControl' wird nur eingeschränkt unterstützt

- 2.6 @ 05.07.2022 09:29
  - Verbesserung: IPS-Status wird nur noch gesetzt, wenn er sich ändert

- 2.5.1 @ 19.06.2022 14:50
  - Fix: Variable 'ChargingStatus' wurde aus zwei Datenfeldern doppelt gesetzt mit u.U. unterschiedlichen Werten
  - Fix: Angabe der Kompatibilität auf 6.2 korrigiert

- 2.5 @ 28.05.2022 11:29
  - erneute Änderung der BMW-API (Änderung der "user-agent")
  - Übersetzung ergänzt
  - update submodule CommonStubs
    Fix: Ausgabe des nächsten Timer-Zeitpunkts

- 2.4 @ 26.05.2022 13:42
  - Übersetzung für Variablenprofile und Instanz-Status fehlerhaft
  - update submodule CommonStubs
  - einige Funktionen (GetFormElements, GetFormActions) waren fehlerhafterweise "protected" und nicht "private"
  - interne Funktionen sind nun entweder private oder nur noch via IPS_RequestAction() erreichbar

- 2.3.4 @ 17.05.2022 15:38
  - update submodule CommonStubs
    Fix: Absicherung gegen fehlende Objekte

- 2.3.3 @ 10.05.2022 15:06
  - update submodule CommonStubs
  - Absicherung Array-Zugriff

- 2.3.2 @ 30.04.2022 18:20
  - Überlagerung von Translate und Aufteilung von locale.json in 3 translation.json (Modul, libs und CommonStubs)
  - Feld "GoogleMap API-Key" verbreitert

- 2.3.1 @ 26.04.2022 12:29
  - Korrektur: self::$IS_DEACTIVATED wieder IS_INACTIVE
  - IPS-Version ist nun minimal 6.0

- 2.3 @ 24.04.2022 14:23
  - Implememtierung einer Update-Logik
  - diverse interne Änderungen

- 2.2.1 @ 16.04.2022 12:22
  - Korrektur der Berechnung von Ladezyklus-Beginn und -Ende
  - Aktualisierung von submodule CommonStubs

- 2.2 @ 13.04.2022 14:51
  - bei einem reinen Elektro-Modell weder Tankinhalt noch kombinierte Reichweite anzeigen
  - Reifendruck anzeigen
  - diverse Verbesserungen zu den Ladeinformationen
  - Anzeige Ladepräferenzen, Abfahrtszeiten
  - Ausgabe der Instanz-Timer unter "Referenzen"
  - potentieller Namenskonflikt behoben (trait CommonStubs)

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
