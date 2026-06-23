$files = @('referensi\\rkpd\\indikator_fix.json','referensi\\rkpd\\indikator_fix.repaired.json','referensi\\rkpd\\indikator_fix.json.bak')
foreach ($f in $files) {
  if (Test-Path $f) {
    try {
      $content = Get-Content $f -Raw
      $null = $content | ConvertFrom-Json
      Write-Output ('{0}: valid JSON' -f $f)
    } catch {
      Write-Output ('{0}: INVALID JSON - {1}' -f $f, $_.Exception.Message)
    }
  } else {
    Write-Output ('{0}: not found' -f $f)
  }
}
