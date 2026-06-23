$files = @('referensi\\rkpd\\indikator_fix.json','referensi\\rkpd\\indikator_fix.repaired.json','referensi\\rkpd\\indikator_fix.json.bak')
foreach ($f in $files) {
  if (Test-Path $f) {
    $text = Get-Content $f -Raw
    $orig = $text
    $text = $text -replace 'SKP\s+D','SKPD'
    $text = $text -replace 'SKPDdan','SKPD dan'
    $text = $text -replace 'SKPD(?=[A-Za-z])','SKPD '
    if ($text -ne $orig) {
      Set-Content -Path $f -Value $text -Encoding UTF8
      Write-Output ('{0}: modified' -f $f)
    } else {
      Write-Output ('{0}: unchanged' -f $f)
    }
  } else {
    Write-Output ('{0}: not found' -f $f)
  }
}
