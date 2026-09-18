<?php
if (!function_exists('fms_get_current_language')) {
    function fms_get_current_language() {
        $uri = $_SERVER['REQUEST_URI'];
        if (preg_match('#/(gr|en|de|it|fr|nl)/?$#', $uri, $matches)) {
            return $matches[1];
        }
        return 'en'; // default fallback
    }
}


if (!function_exists('fms_t')) {
    function fms_t($key) {
        static $translations = null;
        static $lang = null;

        if ($translations === null) {
            $lang = fms_get_current_language();
            $translations = [
                'title' => [
                    'en' => 'Find my surgeon',
                    'de' => 'Chirurgen finden',
                    'it' => 'Trova il mio chirurgo',
                    'fr' => 'Trouvez mon chirurgien',
                    'nl' => 'Vind mijn chirurg',
                    'gr' => 'Βρες τον χειρουργό σου',
                ],
                'subtitle' => [
                    'en' => 'Find your GalaFLEX Internal Bra™ Scaffold surgeon',
                    'de' => 'Finden Sie einen GalaFLEX<br/>Internal Bra™ Scaffold-Chirurgen',
                    'it' => 'Trova il tuo chirurgo Internal Bra',
                    'fr' => 'Trouvez votre chirurgien pour le système GalaFLEX Internal Bra™',
                    'nl' => 'Vind een chirurg met ervaring met de GalaFLEX Internal Bra™ Scaffold',
                    'gr' => 'Βρείτε τον δικό σας χειρουργό για το<br/>Ικρίωμα GalaFLEX Internal Bra™',
                ],
                'select_label' => [
                    'en' => 'Please select your region of interest:',
                    'de' => 'Bitte wählen Sie eine Region aus:',
                    'it' => 'Seleziona la regione di interesse:',
                    'fr' => 'Veuillez sélectionner votre région :',
                    'nl' => 'Selecteer uw regio van interesse:',
                    'gr' => 'Επιλέξτε την περιοχή που σας ενδιαφέρει:',
                ],
                'country' => [
                    'en' => 'Country',
                    'de' => 'Land',
                    'it' => 'Paese',
                    'fr' => 'Pays',
                    'nl' => 'Land',
                    'gr' => 'Χώρα',
                ],
                'city' => [
                    'en' => 'City',
                    'de' => 'Stadt',
                    'it' => 'Città',
                    'fr' => 'Ville',
                    'nl' => 'Stad',
                    'gr' => 'Πόλη',
                ],
                'search' => [
                    'en' => 'Search',
                    'de' => 'Suche',
                    'it' => 'Cerca',
                    'fr' => 'Rechercher',
                    'nl' => 'Zoeken',
                    'gr' => 'Αναζήτηση',
                ],
                'download_strong' => [
                    'en' => 'Download',
                    'de' => 'Laden Sie',
                    'it' => 'Scarica',
                    'fr' => 'Téléchargez',
                    'nl' => 'Download',
                    'gr' => 'Κατεβάστε',
                ],
                'download_text' => [
                    'en' => 'the printable questions sheet<br/>to ask your surgeon',
                    'de' => 'den Fragebogen zum Ausdrucken<br/>herunter, um Ihrem Chirurgen Fragen<br/>zu stellen.',
                    'it' => 'il foglio con le domande<br/>essenziali da fare al tuo chirurgo',
                    'fr' => 'la liste de questions à imprimer<br/>à poser à votre chirurgien',
                    'nl' => 'het afdrukbare vragenblad<br/>om aan uw chirurg te stellen',
                    'gr' => 'το εκτυπώσιμο φύλλο ερωτήσεων για<br/>να απευθυνθείτε στον χειρουργό σας',
                ]
            ];
        }

        return $translations[$key][$lang] ?? $translations[$key]['en'];
    }
}


function fms_get_js_translations() {
    return [
        'select_city' => fms_t('city'),
        'no_cities'   => [
            'en' => 'No cities available',
            'de' => 'Keine Städte verfügbar',
            'it' => 'Nessuna città disponibile',
            'fr' => 'Aucune ville disponible',
            'nl' => 'Geen steden beschikbaar',
            'gr' => 'Δεν υπάρχουν διαθέσιμες πόλεις',
        ][fms_get_current_language()] ?? 'No cities available',
        'ajax_error'  => [
            'en' => 'Error loading cities',
            'de' => 'Fehler beim Laden der Städte',
            'it' => 'Errore durante il caricamento delle città',
            'fr' => 'Erreur lors du chargement des villes',
            'nl' => 'Fout bij het laden van steden',
            'gr' => 'Σφάλμα κατά τη φόρτωση των πόλεων',
        ][fms_get_current_language()] ?? 'Error loading cities',
    ];
}