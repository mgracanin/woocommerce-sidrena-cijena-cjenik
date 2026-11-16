[CmdletBinding()]
param(
    [string]$OutputDirectory = ''
)

$ErrorActionPreference = 'Stop'

if ([string]::IsNullOrWhiteSpace($OutputDirectory)) {
    $OutputDirectory = Join-Path (Join-Path $PSScriptRoot '..') 'dist'
}

$repositoryRoot = [System.IO.Path]::GetFullPath((Join-Path $PSScriptRoot '..'))
$mainPluginFile = Join-Path $repositoryRoot 'sidrena-cijena.php'

if (-not (Test-Path -LiteralPath $mainPluginFile -PathType Leaf)) {
    throw "Main plugin file was not found: $mainPluginFile"
}

$versionMatch = Select-String -LiteralPath $mainPluginFile -Pattern '^\s*\*\s*Version:\s*([^\s]+)\s*$' | Select-Object -First 1
if (-not $versionMatch) {
    throw 'Plugin version could not be read from the file header.'
}

$version = $versionMatch.Matches[0].Groups[1].Value
$resolvedOutputDirectory = [System.IO.Path]::GetFullPath($OutputDirectory)
New-Item -ItemType Directory -Path $resolvedOutputDirectory -Force | Out-Null

$archivePath = Join-Path $resolvedOutputDirectory "sidrena-cijena-v$version.zip"
if (Test-Path -LiteralPath $archivePath) {
    Remove-Item -LiteralPath $archivePath -Force
}

$files = @(
    @{ Source = 'sidrena-cijena.php'; Destination = 'sidrena-cijena/sidrena-cijena.php' },
    @{ Source = 'readme.txt'; Destination = 'sidrena-cijena/readme.txt' },
    @{ Source = 'LICENSE'; Destination = 'sidrena-cijena/LICENSE' },
    @{ Source = 'NOTICE.md'; Destination = 'sidrena-cijena/NOTICE.md' },
    @{ Source = 'includes/cjenik.php'; Destination = 'sidrena-cijena/includes/cjenik.php' },
    @{ Source = 'includes/compliance.php'; Destination = 'sidrena-cijena/includes/compliance.php' },
    @{ Source = 'src/Config.php'; Destination = 'sidrena-cijena/src/Config.php' },
    @{ Source = 'src/Catalog/State.php'; Destination = 'sidrena-cijena/src/Catalog/State.php' },
    @{ Source = 'src/Catalog/StreamWriters.php'; Destination = 'sidrena-cijena/src/Catalog/StreamWriters.php' },
    @{ Source = 'src/Catalog/CatalogEngine.php'; Destination = 'sidrena-cijena/src/Catalog/CatalogEngine.php' }
)

Add-Type -AssemblyName System.IO.Compression
Add-Type -AssemblyName System.IO.Compression.FileSystem
$archive = [System.IO.Compression.ZipFile]::Open($archivePath, [System.IO.Compression.ZipArchiveMode]::Create)

try {
    foreach ($file in $files) {
        $sourcePath = Join-Path $repositoryRoot $file.Source
        if (-not (Test-Path -LiteralPath $sourcePath -PathType Leaf)) {
            throw "Required plugin file was not found: $sourcePath"
        }

        [System.IO.Compression.ZipFileExtensions]::CreateEntryFromFile(
            $archive,
            $sourcePath,
            $file.Destination,
            [System.IO.Compression.CompressionLevel]::Optimal
        ) | Out-Null
    }
}
finally {
    $archive.Dispose()
}

Write-Output $archivePath
