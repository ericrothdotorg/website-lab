<?php
// NOTE: When in mu-plugins, add: defined('ABSPATH') || exit;

// ============================================================================
// STATS ENGINE - Die eine Quelle fuer alle Zahlen
// Muss VOR dem Voting-Snippet und dem Dashboard laden -> Prioritaet niedrig
// ============================================================================

// ----------------------------------------------------------------------------
// GRUNDEINSTELLUNGEN
// ----------------------------------------------------------------------------
// Die Tageswerte kommen aus dem Zehnjahresschnitt der Seite:
//   3.638.531 Views / 3650 Tage = rund 1000
//     567.391 Likes / 3650 Tage = rund 156
//       5.786 Dislikes / 3650 Tage = rund 1,6
// Wenn du die Zahlen spaeter anders willst, nur hier anfassen.

function er_stats_base_rates() {
    return ['view' => 1000, 'like' => 156, 'dislike' => 1.6];
}

// Nur diese Inhaltstypen bekommen ueberhaupt Zaehler.
// Damit kann oembed_cache & Co. nie wieder mitzaehlen.
function er_stats_tracked_types() {
    return ['post', 'page', 'my-interests', 'my-quotes', 'my-traits'];
}

// ----------------------------------------------------------------------------
// DER TAGESWERT
// ----------------------------------------------------------------------------
// Fester Wert pro Datum. Gleiches Datum ergibt immer dieselbe Zahl, egal wie
// oft die Funktion aufgerufen wird. Kein Zufall zur Laufzeit - sonst wuerde
// sich die Anzeige bei jedem Seitenaufruf aendern.

function er_stats_synth_daily($type, $date) {
    $rates = er_stats_base_rates();
    if (!isset($rates[$type])) return 0;

    // Hash des Datums als Zahl zwischen 0 und 1.
    $u = hexdec(substr(hash('sha256', 'er-stats|' . $type . '|' . $date), 0, 8)) / 0xffffffff;

    // Wochentags-Muster. Der Schnitt ueber die Woche bleibt bei 1,0.
    $dow = (int) date('N', strtotime($date));
    $wd  = ($dow >= 6) ? 0.75 : 1.10;

    // Streuung von plus/minus 25 Prozent, damit es nicht wie ein Metronom wirkt.
    $noise = 0.75 + $u * 0.5;

    return max(0, (int) round($rates[$type] * $wd * $noise));
}

// Wie weit der Tag fortgeschritten ist, als weiche Kurve statt linear:
// morgens langsam, mittags am schnellsten, abends wieder flacher.
function er_stats_day_progress() {
    $start   = strtotime(current_time('Y-m-d') . ' 00:00:00');
    $elapsed = (current_time('timestamp') - $start) / DAY_IN_SECONDS;
    $elapsed = min(1, max(0, $elapsed));
    return 0.5 - 0.5 * cos(M_PI * $elapsed);
}

// ----------------------------------------------------------------------------
// TAGEBUCH
// ----------------------------------------------------------------------------
// Der Tagesjob schreibt hier hinein, was er tatsaechlich draufgerechnet hat.
// Die Anzeige liest denselben Eintrag. Dadurch koennen Tageszahl und
// Gesamtstand gar nicht mehr auseinanderlaufen.

function er_stats_log_get($date) {
    $log = get_option('er_stats_synth_log', []);
    return isset($log[$date]) ? $log[$date] : null;
}

function er_stats_log_put($date, $values) {
    $log = get_option('er_stats_synth_log', []);
    $log[$date] = $values;
    if (count($log) > 90) {                       // nur die letzten 90 Tage behalten
        ksort($log);
        $log = array_slice($log, -90, null, true);
    }
    update_option('er_stats_synth_log', $log, false);
}

// Tageswert fuer heute. Falls der Job noch nicht lief, wird er direkt berechnet.
function er_stats_today_target($type) {
    $today = current_time('Y-m-d');
    $entry = er_stats_log_get($today);
    if (is_array($entry) && isset($entry[$type])) {
        return (int) $entry[$type];
    }
    return er_stats_synth_daily($type, $today);
}

// ----------------------------------------------------------------------------
// DER TAGESJOB
// ----------------------------------------------------------------------------
// Loest den alten Wochenjob ab. Verteilt den Tagesbetrag auf die Inhalte und
// schreibt ihn ins Tagebuch. Hier ist Zufall in Ordnung: der Job laeuft einmal,
// das Ergebnis steht danach fest in der Datenbank.

function er_stats_apply_daily() {
    global $wpdb;
    $table = $wpdb->prefix . 'er_post_stats';
    $types = er_stats_tracked_types();
    $in    = "'" . implode("','", array_map('esc_sql', $types)) . "'";
    $date  = current_time('Y-m-d');

    if (er_stats_log_get($date) !== null) return;   // heute schon gelaufen

    $ids = $wpdb->get_col(
        "SELECT ID FROM {$wpdb->posts}
         WHERE post_status = 'publish' AND post_type IN ({$in})"
    );
    if (empty($ids)) return;

    $applied = [];
    foreach (['view', 'like', 'dislike'] as $type) {
        $total = er_stats_synth_daily($type, $date);
        $applied[$type] = $total;
        if ($total <= 0) continue;

        // Betrag zufaellig auf die Inhalte streuen.
        $per = array_fill_keys($ids, 0);
        for ($i = 0; $i < $total; $i++) {
            $per[$ids[array_rand($ids)]]++;
        }

        // Nach Betrag gruppieren, damit es wenige grosse Abfragen statt
        // hunderter kleiner werden.
        $groups = [];
        foreach ($per as $pid => $n) {
            if ($n > 0) $groups[$n][] = (int) $pid;
        }
        foreach ($groups as $n => $pids) {
            $list = implode(',', array_map('intval', $pids));
            $wpdb->query($wpdb->prepare(
                "UPDATE {$table} SET count = count + %d
                 WHERE type = %s AND row_type = 'total' AND post_id IN ({$list})",
                $n, $type
            ));
        }
    }

    er_stats_log_put($date, $applied);
    delete_transient('er_stats_snapshot');
    delete_transient('custom_activity_stats');
}
add_action('er_stats_daily_increment', 'er_stats_apply_daily');

// Einplanen, und die alten Wochenjobs abraeumen.
//
// Die drei wp_unschedule_event() laufen bei jedem Seitenaufruf, obwohl sie seit
// dem Umbau am 03.09.2026 nichts mehr finden. Das ist Absicht und keine offene
// Baustelle: die Jobliste liegt ohnehin im Speicher, die Pruefung kostet also
// praktisch nichts. Eine Merker-Variable waere zwar sauberer, wuerde die
// Aufraeumung aber blockieren, falls die Datenbank mal aus einem aelteren Backup
// zurueckkommt - dann waeren die Wochenjobs wieder da und niemand raeumt sie weg.
// Also stehenlassen.
add_action('init', function() {
    foreach (['increment_views_event', 'increment_likes_event', 'increment_dislikes_event'] as $old) {
        $ts = wp_next_scheduled($old);
        if ($ts) wp_unschedule_event($ts, $old);
    }
    if (!wp_next_scheduled('er_stats_daily_increment')) {
        wp_schedule_event(strtotime('tomorrow 00:05'), 'daily', 'er_stats_daily_increment');
    }
});

// ----------------------------------------------------------------------------
// DIE ZAHLEN FUER ANZEIGE UND DASHBOARD
// ----------------------------------------------------------------------------
// Hier holen sich Shortcodes und Dashboard alles. Eine Funktion, ein
// Zwischenspeicher, keine zweite Kopie irgendwo.

function er_stats_snapshot() {
    $cached = get_transient('er_stats_snapshot');
    if ($cached !== false) return $cached;

    global $wpdb;
    $table    = $wpdb->prefix . 'er_post_stats';
    $today    = current_time('Y-m-d') . ' 00:00:00';
    $progress = er_stats_day_progress();
    $out      = [];

    foreach (['view', 'like', 'dislike'] as $type) {
        // Echte Besucher und echte Klicks von heute.
        $real = (int) $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$table}
             WHERE type = %s AND row_type = 'event' AND created_at >= %s", $type, $today
        ));
        // Der Anteil des Tagesbetrags, der bis jetzt "angefallen" ist.
        $synth = (int) round(er_stats_today_target($type) * $progress);

        $out[$type . 's_today'] = $synth + $real;
        // Die echten Klicks separat, fuer die Kontrollzeile im Dashboard.
        $out['real_' . $type . 's_today'] = $real;
        $out[$type . 's_total'] = (int) $wpdb->get_var($wpdb->prepare(
            "SELECT COALESCE(SUM(count),0) FROM {$table} WHERE type = %s AND row_type = 'total'", $type
        ));
    }

    set_transient('er_stats_snapshot', $out, 5 * MINUTE_IN_SECONDS);
    return $out;
}

// ----------------------------------------------------------------------------
// STARTWERTE FUER NEUE INHALTE
// ----------------------------------------------------------------------------
// Views werden gewuerfelt, Likes und Dislikes daraus berechnet. Genau das war
// vorher der Fehler: alle drei wurden unabhaengig gewuerfelt, dadurch hatten
// manche Seiten 54 Likes je 100 Views und andere 1.

function er_stats_seed_post($post_id) {
    if (wp_is_post_revision($post_id) || wp_is_post_autosave($post_id)) return;
    if (!in_array(get_post_type($post_id), er_stats_tracked_types(), true)) return;

    global $wpdb;
    $table  = $wpdb->prefix . 'er_post_stats';
    $rates  = er_stats_base_rates();
    $views  = rand(5000, 10000);

    // Dieselben Verhaeltnisse wie im Rest der Seite, mit etwas Streuung.
    $r_like    = $rates['like']    / $rates['view'];
    $r_dislike = $rates['dislike'] / $rates['view'];
    $j = fn($salt) => 0.8 + (hexdec(substr(hash('sha256', $salt . '|' . $post_id), 0, 8)) / 0xffffffff) * 0.4;

    $seed = [
        'view'    => $views,
        'like'    => max(0, (int) round($views * $r_like    * $j('like'))),
        'dislike' => max(0, (int) round($views * $r_dislike * $j('dislike'))),
    ];

    foreach ($seed as $type => $count) {
        $exists = $wpdb->get_var($wpdb->prepare(
            "SELECT id FROM {$table} WHERE post_id = %d AND type = %s AND row_type = 'total'",
            $post_id, $type
        ));
        if (!$exists) {
            $wpdb->insert($table, [
                'post_id' => $post_id, 'type' => $type, 'row_type' => 'total',
                'count' => $count, 'created_at' => null,
            ], ['%d', '%s', '%s', '%d', '%s']);
        }
    }
}
add_action('transition_post_status', function($new_status, $old_status, $post) {
    if ($new_status === 'publish' && $old_status !== 'publish') {
        er_stats_seed_post($post->ID);
    }
}, 10, 3);

// ----------------------------------------------------------------------------
// AUFRAEUMEN
// ----------------------------------------------------------------------------
// Zaehler zu geloeschten Seiten und zu Typen, die nicht mitzaehlen sollen,
// werden taeglich entfernt. Sonst sammelt sich derselbe Muell wieder an -
// oembed_cache legt WordPress laufend neu an.

add_action('er_stats_daily_cleanup', function() {
    global $wpdb;
    $table = $wpdb->prefix . 'er_post_stats';
    $in    = "'" . implode("','", array_map('esc_sql', er_stats_tracked_types())) . "'";
    $wpdb->query(
        "DELETE ps FROM {$table} ps
         LEFT JOIN {$wpdb->posts} p ON p.ID = ps.post_id
         WHERE ps.row_type = 'total'
           AND (p.ID IS NULL OR p.post_status <> 'publish' OR p.post_type NOT IN ({$in}))"
    );
}, 20);
