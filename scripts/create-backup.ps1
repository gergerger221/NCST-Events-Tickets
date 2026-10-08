$source = "c:\xampp\htdocs\Integ 2"
$backupZip = "c:\xampp\htdocs\NCST-Events-Tickets-Backup.zip"

Write-Host "Creating backup zip at: $backupZip ..."

if (Test-Path $backupZip) {
    Remove-Item $backupZip -Force
}

# Collect all project files excluding heavy build artifacts and caches
$items = Get-ChildItem -Path $source -Force | Where-Object { $_.Name -notin @('node_modules', '.git', 'dist') }

Compress-Archive -Path $items.FullName -DestinationPath $backupZip -CompressionLevel Optimal

$zipInfo = Get-Item $backupZip
Write-Host "Backup completed successfully!"
Write-Host "Location: $backupZip"
Write-Host "Size: $([math]::Round($zipInfo.Length / 1MB, 2)) MB"
