# unraid-fan-control

Per-fan temperature curves for Unraid.

Most Unraid fan control assumes one fan and one temperature. Real machines have
several fans doing different jobs — case fans pulling air over a drive cage, a
blower on the CPU — and they need different curves, different minimum speeds and
different sensors. This plugin gives each fan its own.

It was written for a **UGREEN NAS DXP6800** running Unraid 7.3, but nothing in it
is specific to that machine. See [Hardware support](#hardware-support).

---

## Why

The plugin exists because of a specific failure mode that generic fan control
handles badly. On the machine this was built for, Dynamix System Autofan was
installed and configured, and yet:

- It was pointed at `pwm2`, which reads **0 rpm**. That looks exactly like an
  empty header, so it appeared to be harmlessly regulating nothing.
- `pwm2` actually had the **CPU blower** on it. Autofan was holding it at PWM 5
  (2% duty), *below the speed at which that fan can turn*. It was stuck in a
  spin-up-stall-repeat loop and reporting 0 rpm as a result.
- The two case fans, on `pwm3` and `pwm4`, were never touched at all. They sat on
  the super-I/O chip's own flat auto curve at a fixed ~860 rpm.
- Array drives sat at **48 °C**, exactly Unraid's warning threshold.

Three fans, two jobs, one of them with a stall threshold that has to be respected.
A single-channel controller cannot express that. After switching to per-fan curves
the same drives settled at **44 °C** with no additional noise at idle.

---

## Features

- **Independent curve per fan** — each fan gets its own temperature source, its
  own low/high points and its own PWM range.
- **Temperature sources** — hottest array HDD, hottest SSD/NVMe, hottest NVMe
  controller, CPU package, or the highest of all.
- **Per-fan stall floor** — a hard clamp the curve can never drive below. Many
  fans stop entirely under some duty and then judder trying to restart. This is a
  safety property of the fan, not a preference, so presets never overwrite it.
- **Emergency trip** — above a set temperature, the curve and the ramp-down
  hysteresis are both bypassed and the fan goes to maximum.
- **Presets** — Silent / Balanced / Cool per temperature source, with the
  reasoning documented rather than hand-waved.
- **Never wakes disks** — HDD temperatures come from Unraid's own `disks.ini`, so
  spun-down drives are never spun up just to be polled. When every HDD is spun
  down, HDD-sourced fans idle at their curve minimum rather than the fail-safe
  duty used for a missing reading.
- **`fan-detect` helper** — maps PWM channels to physical fans and measures each
  fan's stall floor, so you can configure hardware nobody has tested.

---

## Requirements

| | |
|---|---|
| **Unraid** | 6.9 or newer (developed on 7.3.2) |
| **A super-I/O driver** | **Required.** Without one there are no PWM nodes to write to and the plugin can do nothing. See below. |
| Hardware | Any board whose sensor chip exposes writable `pwmN` nodes under `/sys/class/hwmon/` |

### The driver requirement

This is the part people get stuck on. Unraid does **not** load super-I/O sensor
drivers by default, and without one your fans are invisible to software.

**ITE chips (IT86xx / IT87xx)** — this covers UGREEN NAS units and a large share
of consumer boards. Install the **it87-driver** plugin from Community
Applications. On the DXP6800 this binds an `it8613`.

> Keep this plugin installed. It is what creates the `pwm` nodes. Removing it
> leaves `unraid-fan-control` with nothing to control.

**Nuvoton chips (NCT67xx)** — common on ASUS/Gigabyte/MSI boards. Usually already
in the kernel:

```bash
modprobe nct6775
```

Add it to your `go` file to make it persist.

**Verify** before installing this plugin:

```bash
sensors            # should list your chip and its fans
fan-detect         # after install: lists every writable pwm channel
```

If `fan-detect` finds no channels, fix the driver first — nothing else will work.

---

## Install

Plugins → Install Plugin → paste:

```
https://raw.githubusercontent.com/SeraphimSerapis/unraid-fan-control/main/unraid.fan.control.plg
```

Then open **Settings → Fan Control**.

---

## Setting it up

Two things cannot be guessed and must be measured on your machine: which PWM
channel drives which physical fan, and how slowly each fan can turn before it
stalls. `fan-detect` does both.

### 1. See what you have

```bash
fan-detect
```

A channel reading `0 rpm` is ambiguous — it means *either* no fan is connected
*or* a fan is being driven below its stall speed. Do not assume the first one.
That exact ambiguity is what hid a stalled CPU fan on the machine this was
written for.

### 2. Find which fan is which

```bash
fan-detect --map
```

Spins each channel to full for six seconds in turn. Listen.

### 3. Find each fan's stall floor

```bash
fan-detect --sweep 2
```

Ramps that channel down until the fan stops, then restores it. Example output:

```
  PWM    PCT    RPM
  ...
  40     15%    415
  35     13%    311
  30     11%    188
  25     9%     482 / 146  <- unstable
  20     7%     140 / 0

Fan stalled at PWM 20.
```

Set that fan's **stall floor** above the unstable region — `30` here, or `35` for
margin. Set it to `0` for fans that never stall.

### 4. Configure

For each fan set a label, a temperature source, and a preset. Adjust from there.

---

## The presets

Presets set the curve for the selected temperature source. They **never** touch
minimum PWM or the stall floor, because those describe your physical fan.

| Source | Preset | Min speed at | Full speed at |
|---|---|---|---|
| HDD | Silent | 42 °C | 54 °C |
| HDD | **Balanced** | 38 °C | 50 °C |
| HDD | Cool | 34 °C | 46 °C |
| CPU | Silent | 60 °C | 90 °C |
| CPU | **Balanced** | 50 °C | 85 °C |
| CPU | Cool | 45 °C | 75 °C |
| SSD / NVMe | **Balanced** | 50 °C | 70 °C |

**Where these come from.** The HDD numbers are anchored to Unraid's own defaults,
which warn at 45–48 °C and flag critical at 55 °C: the curve should reach full
speed *before* the warning threshold, not inside it. Backblaze's published drive
statistics find no meaningful failure correlation below roughly 50 °C, so cooling
harder than that buys noise rather than drive life. The CPU numbers target
reaching full speed with real headroom below TjMax (100 °C on the part this was
developed against). SSD and NVMe curves start much later on purpose — those parts
throttle around 70–80 °C and chasing their temperature with case fans just adds
noise.

They are defaults, not physics. Your chassis and ambient temperature differ.

---

## Poll interval, and why it is short

The default is **5 seconds**, which may look excessive for a fan controller.

It is not. Drives change temperature over minutes, but a low-power CPU can go
from idle to its throttle point in well under 30 seconds. Measured on the
development machine with a 30-second poll and a synthetic all-core load:

```
t= 15s  cpu= 67C  pwm= 35  fan= 313 rpm
t= 30s  cpu=100C  pwm= 35  fan= 315 rpm   <- TjMax, throttling
t= 45s  cpu= 76C  pwm=218  fan=2812 rpm   <- fan reacts, 30s too late
```

The curve was fine — once it engaged it held 79 °C indefinitely. The *latency*
was the failure. Polling sysfs every 5 seconds costs nothing; hunting is
prevented by hysteresis, which is the correct tool for it, rather than by
reacting slowly.

The **emergency trip** exists as a second backstop for load steps faster than
even a short poll.

---

## Hardware support

Nothing in the daemon is specific to any particular NAS. It discovers controllers
by scanning `/sys/class/hwmon/` for writable `pwmN` nodes, discovers fan channels
from the ones that also expose a tachometer, and takes every curve from config.

| | |
|---|---|
| **Verified** | UGREEN NAS DXP6800 (ITE IT8613), Unraid 7.3.2, i5-1235U |
| **Expected to work** | Any board with an ITE or Nuvoton super-I/O and a driver bound to it — the great majority of consumer Unraid hardware |
| **Will not work** | Boards with no software-controllable PWM; fans wired to headers with no PWM control; BMC/IPMI-managed server boards, where fan control belongs to the BMC |

If your board has more than one candidate controller, pin it with `CHIP="name"`
in the config, matching the `name` shown by `fan-detect`.

Reports from other hardware are welcome — please include `sensors` output and
`fan-detect` output.

---

## Configuration file

`/boot/config/plugins/unraid.fan.control/unraid.fan.control.cfg`

Global keys:

| Key | Meaning |
|---|---|
| `SERVICE` | `enable` / `disable` |
| `INTERVAL` | Poll interval, seconds |
| `HYST` | Ramp-down hysteresis, °C |
| `FANS` | Channels to manage. Empty = auto-discover |
| `CHIP` | Optional. Pin a specific hwmon by name |

Per fan, where `N` is the channel number:

| Key | Meaning |
|---|---|
| `FANN_NAME` | Label shown in the UI |
| `FANN_ENABLED` | `1` to manage, `0` to leave on chip auto |
| `FANN_SOURCE` | `hdd`, `ssd`, `nvme`, `cpu`, `max` |
| `FANN_TLOW` / `FANN_THIGH` | Curve endpoints, °C |
| `FANN_PMIN` / `FANN_PMAX` | PWM range, 0–255 |
| `FANN_FLOOR` | Hard stall clamp. `0` disables |
| `FANN_EMERG` | Emergency trip temperature. `0` disables |

---

## Safety behaviour

- **Stall floor is applied last**, after the curve, the emergency trip and the
  hysteresis, so nothing can drive a fan into a stall.
- **A missing sensor never means "cold".** If the selected source cannot be read,
  the fan holds a safe mid duty rather than idling.
- **Channels are re-asserted every tick.** Some chips silently revert a channel to
  their own automatic curve.
- **Uninstalling hands every channel back** to the chip's automatic mode, so
  removing the plugin never leaves fans stopped.
- **Bogus BIOS fan minimums are cleared** at startup. Some boards ship nonsense
  values — the DXP6800 sets `fan4_min` to 675000 rpm — which make `sensors`
  report a permanent false alarm.

---

## Troubleshooting

**No fan channels found** — no driver is bound. See [Requirements](#requirements).

**A fan reads 0 rpm** — either nothing is connected, or it is stalled. Run
`fan-detect --sweep N`; if it produces rpm at higher duty, there is a fan there
and you need a stall floor.

**Fans hunt up and down** — raise the ramp-down hysteresis.

**Fan never reaches full speed** — check `PMAX`, and check the curve's high point
is a temperature the sensor actually reaches.

**Logs** — everything is logged to syslog under `unraid-fan`:

```bash
grep unraid-fan /var/log/syslog
```

---

## License

MIT. See [LICENSE](LICENSE).

## Disclaimer

This controls cooling hardware. It ships with conservative defaults and several
safety behaviours, but you are responsible for verifying that your machine stays
within safe temperatures. Watch it for a day after changing curves.
