$PluginSlug = "klscms-connector"
$MainFile   = "klscms-connector.php"

# Get version from plugin header
$VersionLine = Get-Content $MainFile | Select-String "Version:\s*[0-9.]+" | Select-Object -First 1
if (-not $VersionLine) {
    Write-Error "Could not find Version header in $MainFile"
    exit 1
}
$Version = $VersionLine.ToString().Split(":")[1].Trim()

$OutputFile = "${PluginSlug}-v${Version}.zip"

Write-Host "Building ${PluginSlug} v${Version}..."

# Remove old zip
if (Test-Path $OutputFile) { Remove-Item $OutputFile }

# Create temp folder with correct WordPress structure
$TempDir = "$env:TEMP\${PluginSlug}"
if (Test-Path $TempDir) { Remove-Item $TempDir -Recurse -Force }
New-Item -ItemType Directory -Path $TempDir | Out-Null
New-Item -ItemType Directory -Path "${TempDir}\${PluginSlug}" | Out-Null

# Copy files to temp/klscms-connector/
$Exclude = @('.git', '.gitignore', '*.zip', 'node_modules', 'build.ps1', 'build.sh')
Get-ChildItem -Path "." | Where-Object { 
    $item = $_ 
    -not ($Exclude | Where-Object { $item.Name -like $_ })
} | Copy-Item -Destination "${TempDir}\${PluginSlug}" -Recurse

# Create zip from temp directory
Compress-Archive -Path "${TempDir}\${PluginSlug}" -DestinationPath $OutputFile

# Cleanup
Remove-Item $TempDir -Recurse -Force

Write-Host "✓ Created: ${OutputFile}"

# Validate structure
$ZipContent = [System.IO.Compression.ZipFile]::OpenRead(
    (Resolve-Path $OutputFile)
)
Write-Host "`nZip structure (first 10 entries):"
$ZipContent.Entries | Select-Object -First 10 | 
    ForEach-Object { Write-Host ("  " + $_.FullName) }
$ZipContent.Dispose()
