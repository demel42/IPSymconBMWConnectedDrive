# IPSymconBMWConnectedDrive
[![Version](https://img.shields.io/badge/Symcon-PHPModul-red.svg)](https://www.symcon.de/service/dokumentation/entwicklerbereich/sdk-tools/sdk-php/)
[![Version](https://img.shields.io/badge/Symcon%20Version-%3E%205.1-green.svg)](https://www.symcon.de/service/dokumentation/installation/)
![Code](https://img.shields.io/badge/Code-PHP-blue.svg)
[![StyleCI](https://github.styleci.io/repos/118332358/shield?branch=master)](https://github.styleci.io/repos/118332358)

Modul für IP-Symcon ab Version 4. Ermöglicht die Kommunikation mit BMW Connected Drive.

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

Mit dem Modul lassen sich Befehle an einen BMW mit BMW Connected Drive schicken und Statusmeldungen über BMW Connected Drive in IP-Symcon (ab Version 4) darstellen. 

### Befehle an BMW Connected Drive senden:  

 - Klimaanlage ein / aus 
 - Türen öffnen /verschließen
 - Lichthupe auslösen
 - Hupe auslösen

### Status Rückmeldung:  

 - Fahrzeugdaten
 - Autobild, einstellbar in Perspektive und Größe
 - Fahrzeugposition in Karte
 - aktueller Breitengard
 - aktueller Längengrad
 - Tankinhalt
 - Tankreichweite
 - Status Veriegelung Türen
 - Kilometerstand	
 - Servicemeldungen
 - Verlauf
	  
## 2. Voraussetzungen

 - IPS 4.x
 - ein BMW mit BMW Connected Drive

## 3. Installation

### a. Laden des Moduls

Die Webconsole von IP-Symcon mit _http://<IP-Symcon IP>:3777/console/_ öffnen. 


Anschließend oben rechts auf das Symbol für den Modulstore klicken

![Store](img/store_icon.png?raw=true "open store")

Im Suchfeld nun

```
BMW Connected Drive
```  

eingeben

![Store](img/module_store_search.png?raw=true "module search")

und schließend das Modul auswählen und auf _Installieren_

![Store](img/install.png?raw=true "install")

drücken.


#### Alternatives Installieren über Modules Instanz

Den Objektbaum _Öffnen_.

![Objektbaum](img/objektbaum.png?raw=true "Objektbaum")	

Die Instanz _'Modules'_ unterhalb von Kerninstanzen im Objektbaum von IP-Symcon (>=Ver. 5.x) mit einem Doppelklick öffnen und das  _Plus_ Zeichen drücken.

![Modules](img/Modules.png?raw=true "Modules")	

![Plus](img/plus.png?raw=true "Plus")	

![ModulURL](img/add_module.png?raw=true "Add Module")
 
Im Feld die folgende URL eintragen und mit _OK_ bestätigen:

```
https://github.com/Wolbolar/IPSymconBMWConnectedDrive
```  
	
Anschließend erscheint ein Eintrag für das Modul in der Liste der Instanz _Modules_    

Es wird im Standard der Zweig (Branch) _master_ geladen, dieser enthält aktuelle Änderungen und Anpassungen.
Nur der Zweig _master_ wird aktuell gehalten.

![Master](img/master.png?raw=true "master") 

Sollte eine ältere Version von IP-Symcon die kleiner ist als Version 5.1 (min 4.3) eingesetzt werden, ist auf das Zahnrad rechts in der Liste zu klicken.
Es öffnet sich ein weiteres Fenster,

![SelectBranch](img/select_branch.png?raw=true "select branch") 

hier kann man auf einen anderen Zweig wechseln, für ältere Versionen kleiner als 5.1 (min 4.3) ist hier
_Old-Version_ auszuwählen. 


### b. Einrichtung in IP-Symcon
	
In IP-Symcon nun _Instanz hinzufügen_ (_Rechtsklick -> Objekt hinzufügen -> Instanz_) auswählen unter der Kategorie, unter der man die BMW Instanz hinzufügen will,
und _BMW_ auswählen.
Im Konfigurationsformular ist der _BMW Connected Drive User_,  das _BMW Connected Drive Passwort_ sowie die _VIN_ (Fahrgestellnummer) zu ergänzen und das Modell einzustellen.

### Beispiel Webfrontansicht
![BMW Webfront](../bmw_webfront.png?raw=true "BMW Webfront")

## 4. Funktionsreferenz

`BMW_GetVehicleData(integer $InstanceID)`

Liest Fahrzeugdaten von BMW Connect Drive aus

`BMW_StartClimateControl(integer $InstanceID)`

Startet Klimaanlage über BMW Connect Drive

`BMW_LockTheDoors(integer $InstanceID)`

Fahrzeug verrieglen über BMW Connect Drive

`BMW_UnlockTheDoors(integer $InstanceID)`

Fahrzeug entrieglen über BMW Connect Drive

`BMW_FlashHeadlights(integer $InstanceID)`

Lichthupe am Fahrzeug auslösen über BMW Connect Drive

`BMW_Honk(integer $InstanceID)`

Hupe am Fahrzeug auslösen über BMW Connect Drive

`BMW_GetCarPictureForAngle(integer $InstanceID, integer $angle)`

Bild vom Fahrzeug für den Winkel _$angle_ abrufen über BMW Connect Drive

`BMW_SetUpdateIntervall(integer $InstanceID, int $Minutes)`

Update-Intervall setzen. Ist der Wert von _Minutes_ gleich 0, wird der konfigurierte Wert verwendet.

`BMW_GetRawData(integer $InstanceID, string $Name)`

Liefert die Daten, die mit den entsprecheden HTTP-Aufrufen gewonnen werden. Sind die gleichen Daten, die früher in den gleichnamigen Variablen abgelegt wurden.

| Name                          | Interface          |
| :---------------------------: | :----------------: |
| bmw_dynamic_interface         | Dynamic            |
| bmw_navigation_interface      | Navigation         |
| bmw_efficiency_interface      | Efficiency         |
| bmw_image_interface           | Image              |
| bmw_mapupdate_interface       | Map Update         |
| bmw_history_interface         | History            |
| bmw_car_interface             | Car                |
| bmw_store_interface           | Store              |
| bmw_specs_interface           | Specs              |
| bmw_service_interface         | Service            |
| bmw_service_partner_interface | Service Partner    |
| bmw_remote_services_interface | Remote Services    |
| bmw_chargingprofile_interface | Chargingprofile    |


## 5. Konfiguration:

### Variablen

| Eigenschaft              | Typ     | Standardwert | Funktion                                  |
| :----------------------: | :-----: | :----------: | :---------------------------------------: |
| user                     | string  |              | Benutzerkennung                           |
| password                 | string  |              | Passwort                                  |
| vin                      | string  |              | Fahrgestellnummer                         |
| bmw_server               | integer | 1            | zu verwendender BMW-Server                |
| model                    | integer | 1            | Modell (Elektisch, Hybrid, Standard)      |
|                          |         |              |                                           |
| active_climate           | boolean | false        | mit Klimatisierung                        |
| active_lock              | boolean | false        | mit Türverschluss                         |
| active_lock_2actions     | boolean | false        | mit Türverschluss (getrennte Aktionen)    |
| active_flash_headlights  | boolean | false        | mit Lichthupe                             |
| active_vehicle_finder    | boolean | false        | mit Fahrzeugsuche (nicht funktionsfähig)  |
| active_lock_data         | boolean | false        | mit Verschluss-Status                     |
| active_honk              | boolean | false        | mit Hupe                                  |
| active_picture           | boolean | true         | mit Anzeige eines Bildes des Fahrzeugs    |
| active_googlemap         | boolean | false        | mit Anzeige der Karte                     |
| googlemap_api_key        | string  |              | GoogleMaps API-Key                        |
| horizontal_mapsize       | integer | 600          |  ... horizontale Größe                    |
| vertical_mapsize         | integer | 400          |  ... vertikale Größe                      |
| active_service           | boolean | false        | mit Angaben zum Service                   |
| active_current_position  | boolean | false        | mit Angaben der aktuellen Position        |
|                          |         |              |                                           |
| UpdateInterval           | integer | 10           | Update-Intervall (in Minuten)             |


## 6. Anhang

###  a. GUIDs und Datenaustausch:

#### BMW:

GUID: `{8FD2A163-E07A-A2A2-58CC-974155FAEE33}` 

### b. Quellen

[BMW-i-Remote](https://github.com/edent/BMW-i-Remote "BMW-i-Remote") _edent_ (MIT)

## 7. Versions-Historie

- 1.13 @ 30.09.2021 18:24 (beta)
  - komplette Umstellung der API

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
