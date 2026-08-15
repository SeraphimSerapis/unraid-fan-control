<?PHP
/* unraid.fan.control - runs after /update.php writes the cfg. Applies changes live. */
$rc = '/usr/local/emhttp/plugins/unraid.fan.control/scripts/rc.fancontrol';

$cfg = @parse_ini_file('/boot/config/plugins/unraid.fan.control/unraid.fan.control.cfg');
$enabled = ($cfg['SERVICE'] ?? 'enable') === 'enable';

// Always stop first so the daemon re-reads config and releases any channel whose
// "manage" flag was just turned off.
exec("$rc stop >/dev/null 2>&1");
if ($enabled) {
  exec("$rc start >/dev/null 2>&1");
  echo "Fan control restarted\n";
} else {
  echo "Fan control stopped\n";
}
