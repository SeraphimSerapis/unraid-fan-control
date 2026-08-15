<?PHP
/* unraid.fan.control - live status for the settings page. */
header('Content-Type: application/json');

$state = '/var/local/emhttp/unraidfan.ini';
$lock  = '/var/run/unraid.fan.control.pid';

$running = false;
if (is_file($lock)) {
  $pid = trim(@file_get_contents($lock));
  if ($pid !== '' && is_dir("/proc/$pid")) $running = true;
}

$out = ['running' => $running, 'fans' => new stdClass(), 'temps' => new stdClass()];

if (is_file($state)) {
  $ini = @parse_ini_file($state, true);
  if (is_array($ini)) {
    $fans = [];
    $temps = [];
    foreach ($ini as $section => $vals) {
      if ($section === 'system') {
        foreach ($vals as $k => $v) if (strpos($k, 'temp_') === 0) $temps[substr($k,5)] = $v;
        continue;
      }
      if (preg_match('/^fan(\d+)$/', $section, $m)) {
        $fans[$m[1]] = [
          'name'    => $vals['name']    ?? '',
          'enabled' => $vals['enabled'] ?? '0',
          'rpm'     => $vals['rpm']     ?? '0',
          'pwm'     => $vals['pwm']     ?? '0',
          'pct'     => $vals['pct']     ?? '0',
          'source'  => $vals['source']  ?? '',
          'temp'    => $vals['temp']    ?? '',
        ];
      }
    }
    if ($fans)  $out['fans']  = $fans;
    if ($temps) $out['temps'] = $temps;
  }
}

echo json_encode($out);
