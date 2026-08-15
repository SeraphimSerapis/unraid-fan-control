<?PHP
/* unraid-fan-control - curve presets.
 *
 * Presets are per TEMPERATURE SOURCE, not per fan, because what a sensible curve
 * looks like depends entirely on what you are cooling. They deliberately do NOT
 * touch PMIN, FLOOR or EMERG: those are properties of your specific fan and
 * hardware, are found by measurement, and must survive a preset change.
 *
 * Anchors used, so these are not arbitrary numbers:
 *
 *   HDD   Unraid's own defaults warn at 45-48C and flag critical at 55C. Vendor
 *         spec for consumer 3.5" drives is typically 5-60C operating. Backblaze's
 *         drive-stats work found no meaningful failure correlation below ~50C.
 *         So: reach full speed at or before 50C, and keep the warning threshold
 *         above the top of the curve rather than inside it.
 *
 *   SSD   NVMe parts throttle around 70-80C and report their own critical point.
 *         They are far more heat tolerant than spinning rust, so the curve starts
 *         much later - chasing NVMe temps with case fans just adds noise.
 *
 *   CPU   Intel/AMD parts throttle at TjMax (100C on the i5-1235U this was built
 *         against). The goal is to reach full speed with headroom to spare, and
 *         to start ramping early enough that a load step does not outrun the fan.
 */

$fan_presets = [

  'hdd' => [
    'silent'   => ['tlow' => 42, 'thigh' => 54, 'pmax' => 200,
                   'desc' => 'Quietest. Lets drives sit warmer; can approach the 48C warning under sustained load.'],
    'balanced' => ['tlow' => 38, 'thigh' => 50, 'pmax' => 255,
                   'desc' => 'Default. Full speed by 50C, comfortably under the 55C critical point.'],
    'cool'     => ['tlow' => 34, 'thigh' => 46, 'pmax' => 255,
                   'desc' => 'Colder and louder. Full speed by 46C, well clear of any warning.'],
  ],

  'ssd' => [
    'silent'   => ['tlow' => 55, 'thigh' => 75, 'pmax' => 200, 'desc' => 'Ignores normal NVMe operating temps entirely.'],
    'balanced' => ['tlow' => 50, 'thigh' => 70, 'pmax' => 255, 'desc' => 'Ramps as drives approach the usual throttle band.'],
    'cool'     => ['tlow' => 45, 'thigh' => 65, 'pmax' => 255, 'desc' => 'Keeps NVMe well below throttling.'],
  ],

  'nvme' => [
    'silent'   => ['tlow' => 55, 'thigh' => 75, 'pmax' => 200, 'desc' => 'Ignores normal NVMe operating temps entirely.'],
    'balanced' => ['tlow' => 50, 'thigh' => 70, 'pmax' => 255, 'desc' => 'Ramps as drives approach the usual throttle band.'],
    'cool'     => ['tlow' => 45, 'thigh' => 65, 'pmax' => 255, 'desc' => 'Keeps NVMe well below throttling.'],
  ],

  'cpu' => [
    'silent'   => ['tlow' => 60, 'thigh' => 90, 'pmax' => 255,
                   'desc' => 'Quiet at idle, but a sudden all-core load can touch the throttle point before the fan catches up.'],
    'balanced' => ['tlow' => 50, 'thigh' => 85, 'pmax' => 255,
                   'desc' => 'Default. Starts ramping well before throttling; measured to hold ~79C under a sustained all-core burn.'],
    'cool'     => ['tlow' => 45, 'thigh' => 75, 'pmax' => 255,
                   'desc' => 'Aggressive. Keeps large thermal headroom at the cost of audible ramping under light load.'],
  ],

  'max' => [
    'silent'   => ['tlow' => 45, 'thigh' => 70, 'pmax' => 200, 'desc' => 'Quietest.'],
    'balanced' => ['tlow' => 40, 'thigh' => 65, 'pmax' => 255, 'desc' => 'Default.'],
    'cool'     => ['tlow' => 35, 'thigh' => 60, 'pmax' => 255, 'desc' => 'Colder and louder.'],
  ],
];

/* Recommended emergency trip per source. Above this the curve and the ramp-down
 * hysteresis are both bypassed and the fan goes straight to maximum. This is the
 * backstop for load steps faster than the poll interval. 0 disables it. */
$fan_emerg = ['hdd' => 55, 'ssd' => 75, 'nvme' => 75, 'cpu' => 90, 'max' => 80];
