# build_zip.ps1 - Build the distribution zip for mod_aiviva (PowerShell).
#
# Usage:
#   .\build_zip.ps1 [version]
#
# If [version] is omitted, the version is read from version.php.
# The output zip uses the Moodle-expected folder name ("aiviva", lowercase) as
# its single root directory, regardless of the development folder's casing.
#
# CRITICAL: this script excludes generate_license.php and license_private.key
# so the private signing key never reaches a customer. Always package with this
# script - never zip the raw folder.
#
# NOTE: amd/, pix/ and backup/ are intentionally INCLUDED (runtime assets).
#
# Requires 7-Zip (7z.exe); Compress-Archive produces ZIPs incompatible with PHP.

param(
    [string]$Version = ""
)

$ErrorActionPreference = "Stop"

$PluginName   = "aiviva"      # Moodle install folder name (zip root, lowercase).
$Frankenstyle = "mod_aiviva"  # Used only for the output zip filename.
$PluginDir    = $PSScriptRoot
$ParentDir    = Split-Path $PluginDir -Parent

# -----------------------------------------------------------------------
# Locate 7-Zip.
# -----------------------------------------------------------------------
$SevenZip = $null
$SevenZipOnPath = $null
$SevenZipCmd = Get-Command "7z" -ErrorAction SilentlyContinue
if ($SevenZipCmd) { $SevenZipOnPath = $SevenZipCmd.Source }
$SevenZipCandidates = @(
    "C:\Program Files\7-Zip\7z.exe",
    "C:\Program Files (x86)\7-Zip\7z.exe",
    $SevenZipOnPath
)
foreach ($candidate in $SevenZipCandidates) {
    if ($candidate -and (Test-Path $candidate)) {
        $SevenZip = $candidate
        break
    }
}
if (-not $SevenZip) {
    Write-Error "7-Zip not found. Install it from https://www.7-zip.org/"
    exit 1
}
Write-Host "Using 7-Zip: $SevenZip"

# -----------------------------------------------------------------------
# Determine version string.
# -----------------------------------------------------------------------
if ($Version -eq "") {
    $versionPhp = Get-Content "$PluginDir\version.php" -Raw
    if ($versionPhp -match "release\s*=\s*'([^']+)'") {
        $Version = $Matches[1]
    } else {
        $Version = "0.0.0"
    }
}

$ZipFile = "$ParentDir\${Frankenstyle}_${Version}.zip"

Write-Host "Building: $Frankenstyle v$Version"
Write-Host "Output:   $ZipFile"

if (-not (Test-Path "$PluginDir\classes\license\validator.php")) {
    Write-Warning "validator.php not found - is the license system installed?"
}

# -----------------------------------------------------------------------
# Patterns to EXCLUDE (relative path prefixes).
# -----------------------------------------------------------------------
$ExcludeRelPatterns = @(
    ".git",
    ".gitignore",
    ".gitattributes",
    ".github",
    "phpunit.xml",
    "phpunit.xml.dist",
    ".phpunit.result.cache",
    ".vscode",
    ".idea",
    "node_modules",
    "package.json",
    "package-lock.json",
    "Gruntfile.js",
    ".grunt",
    "build_zip.ps1",
    "build_zip.sh",
    ".distignore",
    "generate_license.php",
    "license_private.key",
    ".claude",
    "CLAUDE.md"
)
$ExcludeExtensions = @(".md")

# -----------------------------------------------------------------------
# Collect files to include.
# -----------------------------------------------------------------------
$allFiles = Get-ChildItem -Path $PluginDir -Recurse -File

$included = @()
foreach ($file in $allFiles) {
    $rel  = $file.FullName.Substring($PluginDir.Length + 1)
    $skip = $false

    if ($ExcludeExtensions -contains $file.Extension.ToLower()) { $skip = $true }

    if (-not $skip) {
        foreach ($pattern in $ExcludeRelPatterns) {
            if ($rel -eq $pattern -or $rel.StartsWith($pattern + "\")) {
                $skip = $true; break
            }
        }
    }

    if (-not $skip) { $included += $file }
}

Write-Host "Including $($included.Count) files..."

# Hard guard: never ship a private key or the licence generator.
# Matches by extension and basename anywhere in the tree, not just at the root,
# so renaming or moving the key does not silently defeat the check.
foreach ($f in $included) {
    $rel  = $f.FullName.Substring($PluginDir.Length + 1)
    $leaf = Split-Path $rel -Leaf
    if ($leaf -like "*.key" -or $leaf -like "*.pem" -or $leaf -eq "generate_license.php") {
        Write-Error "REFUSING TO BUILD: '$rel' looks like a signing key or the licence generator and would be included in the zip."
        exit 1
    }
}

# Second guard: warn loudly if the private key is present in the working tree at
# all, even when correctly excluded from the zip. It does not belong here.
$strayKeys = Get-ChildItem -Path $PluginDir -Recurse -File -Include *.key, *.pem -ErrorAction SilentlyContinue
if ($strayKeys) {
    Write-Warning "Private key material found inside the plugin tree (excluded from the zip, but move it out):"
    foreach ($k in $strayKeys) { Write-Warning "  $($k.FullName)" }
}

# -----------------------------------------------------------------------
# Stage files under "<PluginName>\" so the ZIP root matches the install name.
# -----------------------------------------------------------------------
$TempDir   = Join-Path $env:TEMP "moodle_plugin_build_$(Get-Random)"
$StageRoot = Join-Path $TempDir $PluginName
New-Item -ItemType Directory -Path $StageRoot -Force | Out-Null

foreach ($file in $included) {
    $rel      = $file.FullName.Substring($PluginDir.Length + 1)
    $destPath = Join-Path $StageRoot $rel
    $destDir  = Split-Path $destPath -Parent
    if (-not (Test-Path $destDir)) {
        New-Item -ItemType Directory -Path $destDir -Force | Out-Null
    }
    Copy-Item $file.FullName -Destination $destPath
}

# -----------------------------------------------------------------------
# Create the ZIP with 7-Zip.
# -----------------------------------------------------------------------
if (Test-Path $ZipFile) { Remove-Item $ZipFile -Force }

Push-Location $TempDir
try {
    & $SevenZip a -tzip -mx=5 $ZipFile "$PluginName\" | Out-Null
    if ($LASTEXITCODE -ne 0) {
        throw "7-Zip exited with code $LASTEXITCODE"
    }
} finally {
    Pop-Location
}

Remove-Item $TempDir -Recurse -Force

$size = [math]::Round((Get-Item $ZipFile).Length / 1KB, 1)
Write-Host ""
Write-Host "Done: ${size} KB  $ZipFile"
