# Test Message Sink

Minimalmodul zur Reproduktion eines Fehlers in IP-Symcon.

## Beobachtung

Wird `$this->LogMessage()` — die von `IPSModule` geerbte, typisierte Methode —
innerhalb von `MessageSink()` aufgerufen, meldet Symcon:

```
Warning: InstanceInterface is not available
InstanzManager: Kann Schnittstellen-Instanz nicht erstellen
```

In jedem anderen Ausführungskontext desselben Moduls (`ApplyChanges()`,
`RequestAction()`, Timer-Callback) funktioniert derselbe Aufruf unauffällig.
Die globale, untypisierte `IPS_LogMessage()` funktioniert auch im `MessageSink`.

Aufgefallen ist das im Modul *Simple Locale*, das auf `VM_UPDATE` verfolgter
String-Variablen reagiert. Dort wurde als Umgehung eingebaut, im
`MessageSink`-Kontext auf `IPS_LogMessage()` auszuweichen — mit dem Nachteil,
dass die Meldung dann ohne Schweregrad-Farbcodierung als „Custom" erscheint.

## Zwei Fälle

Das Formular hat einen Schalter, der den einzigen Unterschied zwischen beiden
Abläufen herstellt:

| | Ablauf im `MessageSink()` | Ergebnis |
|---|---|---|
| **Fall 1** (Schalter aus) | nur `$this->LogMessage()` | läuft sauber durch |
| **Fall 2** (Schalter an) | erst `IPS_SetProperty()` + `IPS_ApplyChanges()` auf die **eigene** Instanz, dann `$this->LogMessage()` | zu prüfen |

Fall 1 wurde geprüft und ist unauffällig — der Log-Eintrag erscheint wie
erwartet. Das Modul, in dem der Fehler auftrat, entspricht Fall 2: sein
`VM_UPDATE`-Pfad persistiert die aktualisierte Zeile per `IPS_SetProperty()` +
`IPS_ApplyChanges()` und läuft damit aus dem `MessageSink` heraus erneut durch
`ApplyChanges()`. Der Verdacht ist, dass erst dieser Wiedereintritt die
Instanz-Schnittstelle unbrauchbar macht.

## Reproduktion

1. Instanz dieses Moduls anlegen.
2. Im Konfigurationsformular eine beliebige Variable wählen, „Übernehmen".
3. Den Wert dieser Variable ändern — etwa von Hand im Objektbaum.
4. Meldungs-Log beobachten. → **Fall 1**, erwartungsgemäß ohne Fehler.
5. Schalter einschalten, „Übernehmen", Schritt 3 wiederholen. → **Fall 2**.

Erwartet in beiden Fällen: ein Eintrag `Wert von Variable <ID>: <Wert>`.

## Aufbau

Das Modul tut bewusst nichts außer dem Nötigen:

* `Create()` — eine Property für die Variablen-ID, ein Attribut für die
  Buchführung der Registrierung.
* `ApplyChanges()` — meldet die vorherige Variable ab und die gewählte auf
  `VM_UPDATE` an.
* `MessageSink()` — ein `SendDebug()` als Nachweis, dass der Sink erreicht
  wurde (Debug-Fenster, nicht Log, am Fehlerbild unbeteiligt), danach der zu
  untersuchende `$this->LogMessage()`-Aufruf.

Kein Timer, keine Statusvariablen, keine weitere Logik.

## Gegenprobe

Um zu bestätigen, dass es am Kontext liegt und nicht am Aufruf selbst, lässt
sich die Zeile im `MessageSink` versuchsweise ersetzen:

```php
IPS_LogMessage('Test Message Sink #' . $this->InstanceID, 'Wert: ' . GetValue($SenderID));
```

Diese Variante schreibt aus demselben Kontext zuverlässig ins Log.
