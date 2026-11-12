$ErrorActionPreference = 'Stop'

$extensionDirectory = Split-Path -Parent $MyInvocation.MyCommand.Path
$manifestPath = Join-Path $extensionDirectory 'manifest.json'
$manifest = Get-Content -Raw -Path $manifestPath | ConvertFrom-Json
$outputDirectory = Join-Path $extensionDirectory 'dist'
$packagePath = Join-Path $outputDirectory "grompil-bookmarks-$($manifest.version).zip"
New-Item -ItemType Directory -Force -Path $outputDirectory | Out-Null

if (Test-Path -LiteralPath $packagePath) {
    Remove-Item -LiteralPath $packagePath -Force
}

Add-Type -AssemblyName System.IO.Compression.FileSystem
$archive = [System.IO.Compression.ZipFile]::Open($packagePath, [System.IO.Compression.ZipArchiveMode]::Create)
try {
    $packageFiles = @('manifest.json', 'popup.html', 'popup.css', 'popup.js', 'bookmark-sync.js', 'background.js', 'content-script.js') | ForEach-Object {
        Join-Path $extensionDirectory $_
    }
    $packageFiles += Get-ChildItem -LiteralPath (Join-Path $extensionDirectory 'icons') -File | Select-Object -ExpandProperty FullName

    foreach ($file in $packageFiles) {
        $relativePath = [System.IO.Path]::GetRelativePath($extensionDirectory, $file).Replace('\', '/')
        [System.IO.Compression.ZipFileExtensions]::CreateEntryFromFile($archive, $file, $relativePath, [System.IO.Compression.CompressionLevel]::Optimal) | Out-Null
    }
}
finally {
    $archive.Dispose()
}

Write-Output "Chrome Web Store package created: $packagePath"
