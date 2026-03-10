<?php
// ======== UZUPEŁNIJ DANYMI Z CYBERFOLKS ========
define('DB_HOST', 'localhost');
define('DB_NAME', 'sckstrzegom_harmonogram');
define('DB_USER', 'sckstrzegom_harmonogram_user');
define('DB_PASS', 'TX3-N(QC!qr7r-U*');
define('DB_CHARSET', 'utf8mb4');

define('APP_NAME', 'SCK Harmonogram');

define('DEFAULT_SHIFT_TYPES', [
    'standard'   => ['label'=>'Zmiana standardowa','color'=>'#fff7ed','text'=>'#7c2d12','start'=>'07:30','end'=>'16:00'],
    'urlop'      => ['label'=>'Urlop',             'color'=>'#22c55e','text'=>'#fff',   'start'=>'',     'end'=>''],
    'wolne'      => ['label'=>'Wolne (W)',         'color'=>'#f1f5f9','text'=>'#64748b','start'=>'',     'end'=>''],
    'chorobowe'  => ['label'=>'Chorobowe',         'color'=>'#fbbf24','text'=>'#78350f','start'=>'',     'end'=>''],
    'szkolenie'  => ['label'=>'Szkolenie',         'color'=>'#06b6d4','text'=>'#fff',   'start'=>'08:00','end'=>'16:00'],
    'wydarzenie' => ['label'=>'Wydarzenie',        'color'=>'#ea580c','text'=>'#fff',   'start'=>'09:00','end'=>'17:00'],
    'swieto'     => ['label'=>'Święto',            'color'=>'#dc2626','text'=>'#fff',   'start'=>'',     'end'=>''],
    'brak'       => ['label'=>'Brak dyżuru (X)',   'color'=>'#cbd5e1','text'=>'#475569','start'=>'',     'end'=>''],
    'kino'       => ['label'=>'Kino',              'color'=>'#f97316','text'=>'#fff',   'start'=>'14:00','end'=>'18:30'],
    'koncert'    => ['label'=>'Koncert',           'color'=>'#ea580c','text'=>'#fff',   'start'=>'17:00','end'=>'21:30'],
    'bazylika'   => ['label'=>'Bazylika',          'color'=>'#7c3aed','text'=>'#fff',   'start'=>'12:30','end'=>'21:30'],
    'dyzur'      => ['label'=>'Dyżur',             'color'=>'#0f172a','text'=>'#fbbf24','start'=>'',     'end'=>''],
]);

define('FRACTIONS', ['1.00'=>160,'0.75'=>120,'0.50'=>80,'0.25'=>40]);
define('DAY_NAMES_PL', ['nd','pn','wt','śr','cz','pt','so']);
define('MONTH_NAMES_PL', [1=>'Styczeń',2=>'Luty',3=>'Marzec',4=>'Kwiecień',5=>'Maj',6=>'Czerwiec',7=>'Lipiec',8=>'Sierpień',9=>'Wrzesień',10=>'Październik',11=>'Listopad',12=>'Grudzień']);
