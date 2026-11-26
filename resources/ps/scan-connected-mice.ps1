# Read-only enumeration of present mouse / HID / USB devices for live pairing.
# Emits a single JSON array on stdout. No CIM (it hangs from a non-interactive
# PHP process), no driver installation and no file execution.
$ErrorActionPreference = 'SilentlyContinue'

$detectedAt = (Get-Date).ToUniversalTime().ToString('o')

try {
    $presentDevices = @(Get-PnpDevice -PresentOnly -ErrorAction Stop | Where-Object {
        ($_.Class -in @('Mouse', 'HIDClass', 'USB')) -or ($_.InstanceId -match 'VID_')
    })
} catch {
    '[]'
    exit 0
}

$results = @()

foreach ($device in $presentDevices) {
    try {
        $instanceId = [string]$device.InstanceId
        $deviceClass = [string]$device.Class
        $name = [string]$device.FriendlyName
        if (-not $name) { $name = [string]$device.Description }

        # Never use $pid: that is a read-only PowerShell process-ID variable.
        $vendorId = $null
        $productId = $null
        if ($instanceId -match 'VID_([0-9A-Fa-f]{4})') { $vendorId = $matches[1].ToUpperInvariant() }
        if ($instanceId -match 'PID_([0-9A-Fa-f]{4})') { $productId = $matches[1].ToUpperInvariant() }

        # Get-PnpDeviceProperty costs ~2 s per device in this environment. For
        # live pairing the InstanceId already carries VID/PID, so a single PnP
        # enumeration is enough and the scan stays responsive. Rich hardware ID
        # / driver details are available from the separate USB scan endpoint.
        $hardwareIds = @()
        if ($instanceId -ne '') { $hardwareIds = @($instanceId) }

        if (-not $name) { $name = 'Unknown Device' }

        $description = [string]$device.Description

        $manufacturer = [string]$device.Manufacturer
        $lowerName = $name.ToLowerInvariant()
        $isInput = ($deviceClass -eq 'Mouse') -or ($deviceClass -eq 'HIDClass') -or
            ($lowerName -match 'mouse|keyboard|hid|pointing|trackpad|touchpad')

        $results += [pscustomobject]@{
            name         = $name
            class        = $deviceClass
            status       = [string]$device.Status
            instance_id  = $instanceId
            vid          = $vendorId
            pid          = $productId
            hardware_ids = $hardwareIds
            manufacturer = if ($manufacturer) { $manufacturer } else { $null }
            location     = $null
            description  = if ($description) { $description } else { $null }
            is_input     = [bool]$isInput
            detected_at  = $detectedAt
        }
    } catch {
        # One problematic device must never abort the whole scan.
        continue
    }
}

if ($results.Count -eq 0) {
    '[]'
} else {
    ConvertTo-Json -InputObject $results -Depth 5 -Compress
}
