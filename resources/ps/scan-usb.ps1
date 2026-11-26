# Enumerates present USB devices with optional driver and PnP property details.
# Emits a single JSON array on stdout. Intentionally read-only.
$ErrorActionPreference = 'SilentlyContinue'

function Get-DevicePropertyData {
    param(
        [Parameter(Mandatory = $true)] $Properties,
        [Parameter(Mandatory = $true)][string] $KeyName
    )

    foreach ($property in $Properties) {
        if ($property.KeyName -eq $KeyName) {
            return $property.Data
        }
    }

    return $null
}

# Driver metadata is read from PnP properties rather than Win32_PnPSignedDriver.
# The CIM class can hang when PowerShell is launched from a non-interactive PHP
# process, while DEVPKEY_Device_Driver* properties are fast and need no admin rights.

$receiverKeywordPattern = 'receiver|dongle|wireless|transceiver|unifying|nano|lightspeed|bolt'
$commonReceiverVids = @('046D', '1532', '045E')

try {
    $presentDevices = @(Get-PnpDevice -PresentOnly -ErrorAction Stop | Where-Object { $_.InstanceId -like 'USB*' })
} catch {
    '[]'
    exit 0
}

$detectedAt = (Get-Date).ToUniversalTime().ToString('o')
$results = @()

foreach ($device in $presentDevices) {
    $instanceId = [string]$device.InstanceId

    # One property query per device: repeated per-key queries are very slow when
    # PowerShell is started from a non-interactive PHP process.
    $deviceProperties = @()
    try {
        $deviceProperties = @(Get-PnpDeviceProperty -InstanceId $instanceId -ErrorAction SilentlyContinue)
    } catch {
        $deviceProperties = @()
    }

    # Never use $pid here: PowerShell's $PID is a read-only process-ID variable.
    $vendorId = $null
    $productId = $null
    if ($instanceId -match 'VID_([0-9A-Fa-f]{4})') { $vendorId = $matches[1].ToUpperInvariant() }
    if ($instanceId -match 'PID_([0-9A-Fa-f]{4})') { $productId = $matches[1].ToUpperInvariant() }

    $hardwareIds = @()
    $hardwareIdData = Get-DevicePropertyData -Properties $deviceProperties -KeyName 'DEVPKEY_Device_HardwareIds'
    if ($null -ne $hardwareIdData) {
        $hardwareIds = @($hardwareIdData | ForEach-Object { [string]$_ })
    }
    if ($hardwareIds.Count -eq 0) { $hardwareIds = @($instanceId) }

    $serialValue = Get-DevicePropertyData -Properties $deviceProperties -KeyName 'DEVPKEY_Device_SerialNumber'
    $locationValue = Get-DevicePropertyData -Properties $deviceProperties -KeyName 'DEVPKEY_Device_LocationInfo'
    $classValue = Get-DevicePropertyData -Properties $deviceProperties -KeyName 'DEVPKEY_Device_Class'
    $deviceDescValue = Get-DevicePropertyData -Properties $deviceProperties -KeyName 'DEVPKEY_Device_DeviceDesc'
    $busDescValue = Get-DevicePropertyData -Properties $deviceProperties -KeyName 'DEVPKEY_Device_BusReportedDeviceDesc'

    $deviceClass = [string]$device.Class
    if ($classValue) { $deviceClass = [string]$classValue }

    $name = [string]$device.FriendlyName
    if (-not $name -and $busDescValue) { $name = [string]$busDescValue }
    if (-not $name -and $deviceDescValue) { $name = [string]$deviceDescValue }
    if (-not $name) { $name = [string]$device.Description }
    if (-not $name) { $name = 'Unknown Device' }

    $description = [string]$device.Description
    if (-not $description -and $deviceDescValue) { $description = [string]$deviceDescValue }
    if (-not $description -and $busDescValue) { $description = [string]$busDescValue }

    $manufacturer = [string]$device.Manufacturer

    $driverNameValue = Get-DevicePropertyData -Properties $deviceProperties -KeyName 'DEVPKEY_Device_DriverDesc'
    $driverVersion = Get-DevicePropertyData -Properties $deviceProperties -KeyName 'DEVPKEY_Device_DriverVersion'
    $driverProvider = Get-DevicePropertyData -Properties $deviceProperties -KeyName 'DEVPKEY_Device_DriverProvider'

    $driverName = if ($driverNameValue) { [string]$driverNameValue } else { $null }
    $driverVersion = if ($driverVersion) { [string]$driverVersion } else { $null }
    $driverProvider = if ($driverProvider) { [string]$driverProvider } else { $null }
    if (-not $manufacturer -and $driverProvider) { $manufacturer = $driverProvider }

    # Candidate only: a wireless receiver cannot be confirmed from a name alone.
    $isReceiver = ($name -match $receiverKeywordPattern) -or
        (($deviceClass -eq 'HIDClass') -and ($vendorId -in $commonReceiverVids))

    $lowerName = $name.ToLowerInvariant()
    if ($lowerName -match 'mouse|receiver|dongle|wireless|transceiver|unifying') {
        $category = 'wireless_mouse_receiver'
    } elseif ($lowerName -match 'keyboard') {
        $category = 'keyboard'
    } elseif ($lowerName -match 'hub') {
        $category = 'usb_hub'
    } elseif ($lowerName -match 'storage|disk|drive') {
        $category = 'storage'
    } elseif ($lowerName -match 'camera|webcam') {
        $category = 'camera'
    } elseif ($lowerName -match 'audio|sound|headset') {
        $category = 'audio'
    } elseif ($deviceClass) {
        $category = $deviceClass.ToLowerInvariant()
    } else {
        $category = 'other'
    }

    $results += [pscustomobject]@{
        name                = $name
        manufacturer        = $manufacturer
        description         = $description
        vid                 = $vendorId
        pid                 = $productId
        hardware_ids        = $hardwareIds
        device_instance_id  = $instanceId
        serial_number       = if ($serialValue) { [string]$serialValue } else { $null }
        usb_class           = if ($deviceClass) { $deviceClass } else { $null }
        driver_name         = $driverName
        driver_version      = $driverVersion
        driver_provider     = $driverProvider
        status              = [string]$device.Status
        location            = if ($locationValue) { [string]$locationValue } else { $null }
        category            = $category
        is_receiver         = [bool]$isReceiver
        detected_at         = $detectedAt
    }
}

if ($results.Count -eq 0) {
    '[]'
} else {
    ConvertTo-Json -InputObject $results -Depth 5 -Compress
}
