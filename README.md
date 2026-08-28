# Test LogMessage

Minimalmodul zur Reproduktion eines Fehlers in IP-Symcon: `$this->LogMessage()`
scheitert innerhalb von `MessageSink()`.

## Kurzfassung

Wird die von `IPSModule` geerbte, typisierte Methode `$this->LogMessage()` aus
`MessageSink()` heraus aufgerufen, meldet Symcon:

```
Warning: InstanceInterface is not available
InstanzManager: Kann Schnittstellen-Instanz nicht erstellen
```

In jedem anderen Ausführungskontext desselben Moduls (`ApplyChanges()`,
`RequestAction()`, Timer-Callback) funktioniert derselbe Aufruf unauffällig.
Die globale, untypisierte `IPS_LogMessage()` funktioniert auch im `MessageSink`
— dafür ohne Schweregrad und damit ohne Farbcodierung im Meldungs-Log.

Der Schweregrad spielt keine Rolle: der Aufruf scheitert mit `KL_ERROR`,
`KL_WARNING` und `KL_MESSAGE` gleichermaßen. Es ist der Kontext, nicht das
Argument.

## Reproduktion

1. Instanz von __Test Message Sink__ anlegen.
2. Im Konfigurationsformular eine beliebige Variable wählen, „Übernehmen".
3. Den Wert dieser Variable ändern — etwa von Hand im Objektbaum.
4. Meldungs-Log beobachten.

Ausführlich, inklusive Gegenprobe mit `IPS_LogMessage()`, in der
[Modul-Dokumentation](Test%20Message%20Sink).

## Herkunft

Aufgefallen im Modul *Simple Locale*, das auf `VM_UPDATE` verfolgter
String-Variablen reagiert und Fehler der Anbieter-Kommunikation als `KL_ERROR`
protokollieren möchte. Dort ist als Umgehung eingebaut, im
`MessageSink`-Kontext auf `IPS_LogMessage()` auszuweichen — mit dem Nachteil,
dass die Meldung dann als graues „Custom" statt als rotes „FEHLER" erscheint.

## Enthaltene Module

- __Test Message Sink__ ([Dokumentation](Test%20Message%20Sink))  
	Wählt eine Variable, überwacht sie auf `VM_UPDATE` und protokolliert ihren
	Wert im `MessageSink` per `$this->LogMessage()`. Sonst nichts.
