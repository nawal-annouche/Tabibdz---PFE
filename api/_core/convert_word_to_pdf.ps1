param(
  [Parameter(Mandatory = $true)][string]$InputPath,
  [Parameter(Mandatory = $true)][string]$OutputPath
)

$word = $null
$doc = $null

try {
  $resolvedInput = (Resolve-Path -LiteralPath $InputPath).Path
  $outputDir = Split-Path -Path $OutputPath -Parent
  if (!(Test-Path -LiteralPath $outputDir)) {
    New-Item -ItemType Directory -Path $outputDir | Out-Null
  }

  $word = New-Object -ComObject Word.Application
  $word.Visible = $false
  $word.DisplayAlerts = 0
  $doc = $word.Documents.Open($resolvedInput, $false, $true)
  $wdFormatPDF = 17
  $doc.SaveAs([ref]$OutputPath, [ref]$wdFormatPDF)
}
finally {
  if ($doc -ne $null) {
    $doc.Close()
    [System.Runtime.InteropServices.Marshal]::ReleaseComObject($doc) | Out-Null
  }
  if ($word -ne $null) {
    $word.Quit()
    [System.Runtime.InteropServices.Marshal]::ReleaseComObject($word) | Out-Null
  }
}
