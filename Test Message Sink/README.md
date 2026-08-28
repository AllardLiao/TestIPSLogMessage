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

## Vier Fälle

Die Schalter im Formular stellen den jeweiligen Unterschied her und lassen sich
einzeln oder gemeinsam einschalten:

| | Ablauf im `MessageSink()` | Ergebnis |
|---|---|---|
| **Fall 1** (alles aus) | nur `$this->LogMessage()` | läuft sauber durch |
| **Fall 2** | erst `IPS_SetProperty()` + `IPS_ApplyChanges()` auf die **eigene** Instanz, dann loggen | läuft sauber durch |
| **Fall 3** | erst in die **überwachte Variable** zurückschreiben — löst eine verschachtelte Zustellung derselben Nachricht aus —, dann loggen | läuft sauber durch |
| **Fall 2 + 3** | beides zusammen | läuft sauber durch |
| **Fall 4** (Sekunden > 0) | den `MessageSink` künstlich verzögern, dann loggen | läuft sauber durch |

**Stand der Untersuchung: nicht reproduzierbar.** Alle vier Fälle wurden
geprüft, einzeln und kombiniert — durchweg ohne Fehler. `$this->LogMessage()`
funktioniert im `MessageSink` also auch dann, wenn die Instanz sich darin selbst
neu anwendet, wenn die Zustellung verschachtelt ist, und wenn der Durchlauf
Sekunden dauert.

Damit ist die ursprüngliche Zuordnung fraglich. Die Warnung wurde am 17.08.2026
in einem laufenden System beobachtet und dem Log-Aufruf zugeschrieben; ein
isolierter Nachweis war es nicht. Möglich ist, dass sie von einem anderen Aufruf
im selben Pfad stammte und nur zeitlich zusammenfiel.

Dieses Repository dokumentiert damit vor allem, was der Auslöser **nicht** ist.

## Reproduktion

1. Instanz dieses Moduls anlegen.
2. Im Konfigurationsformular eine beliebige **String-Variable** wählen,
   „Übernehmen".
3. Den Wert dieser Variable ändern — etwa von Hand im Objektbaum.
4. Meldungs-Log beobachten.
5. Für die weiteren Fälle den jeweiligen Schalter setzen, „Übernehmen",
   Schritt 3 wiederholen.

Erwartet in allen Fällen: ein Eintrag `Wert von Variable <ID>: <Wert>`.

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
