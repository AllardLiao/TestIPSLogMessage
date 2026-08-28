<?php

declare(strict_types=1);

/*
 * Minimalmodul zur Reproduktion eines Fehlers in IP-Symcon.
 *
 * BEOBACHTUNG: Wird $this->LogMessage() - die von IPSModule geerbte, typisierte
 * Methode - innerhalb von MessageSink() aufgerufen, meldet Symcon
 *
 *     Warning: InstanceInterface is not available
 *     InstanzManager: Kann Schnittstellen-Instanz nicht erstellen
 *
 * In anderen Ausfuehrungskontexten desselben Moduls (ApplyChanges,
 * RequestAction, Timer-Callback) funktioniert derselbe Aufruf unauffaellig.
 * Die globale, untypisierte IPS_LogMessage() funktioniert auch im MessageSink.
 *
 * Dieses Modul tut bewusst NICHTS ausser dem Noetigen: eine Variable im
 * Konfigurationsformular waehlen, sie auf VM_UPDATE ueberwachen und im
 * MessageSink ihren Wert per $this->LogMessage() protokollieren.
 *
 * REPRODUKTION:
 *   1. Instanz anlegen, im Formular eine beliebige Variable waehlen, uebernehmen.
 *   2. Den Wert dieser Variable aendern (z.B. von Hand im Objektbaum).
 *   3. Meldungs-Log beobachten.
 *
 * Das SendDebug() dient nur als Nachweis, dass der MessageSink ueberhaupt
 * erreicht wurde - es schreibt ins Debug-Fenster, nicht ins Log, und ist am
 * Fehlerbild unbeteiligt.
 */
class TestMessageSink extends IPSModuleStrict
{
    private const PROPERTY_VARIABLE_ID = 'VariableID';

    // Merkt sich, welche Variable aktuell ueberwacht wird, damit ein Wechsel im
    // Formular keine verwaiste Registrierung hinterlaesst.
    private const ATTRIBUTE_REGISTERED_ID = 'RegisteredVariableID';

    public function Create(): void
    {
        //Never delete this line!
        parent::Create();

        $this->RegisterPropertyInteger(self::PROPERTY_VARIABLE_ID, 0);
        $this->RegisterAttributeInteger(self::ATTRIBUTE_REGISTERED_ID, 0);
    }

    public function Destroy(): void
    {
        //Never delete this line!
        parent::Destroy();
    }

    public function ApplyChanges(): void
    {
        //Never delete this line!
        parent::ApplyChanges();

        $previousID = $this->ReadAttributeInteger(self::ATTRIBUTE_REGISTERED_ID);
        if ($previousID !== 0) {
            $this->UnregisterMessage($previousID, VM_UPDATE);
        }

        $variableID = $this->ReadPropertyInteger(self::PROPERTY_VARIABLE_ID);
        if ($variableID !== 0 && IPS_VariableExists($variableID)) {
            $this->RegisterMessage($variableID, VM_UPDATE);
        }

        $this->WriteAttributeInteger(self::ATTRIBUTE_REGISTERED_ID, $variableID);
    }

    public function MessageSink(int $TimeStamp, int $SenderID, int $Message, array $Data): void
    {
        //Never delete this line!
        parent::MessageSink($TimeStamp, $SenderID, $Message, $Data);

        if ($Message !== VM_UPDATE) {
            return;
        }

        // Nachweis, dass der MessageSink erreicht wurde (Debug-Fenster, nicht Log).
        $this->SendDebug('MessageSink', 'VM_UPDATE von Variable ' . $SenderID, 0);

        // DAS ist der zu untersuchende Aufruf.
        $this->LogMessage(
            'Wert von Variable ' . $SenderID . ': ' . GetValue($SenderID),
            KL_MESSAGE
        );
    }
}
