# Scan referensi/** and tmp_* files, backup any changed file, apply replacements, and report summary
$targets = @()
$targets += Get-ChildItem -Path 'referensi' -Recurse -File -ErrorAction SilentlyContinue
$targets += Get-ChildItem -Path . -Recurse -File -Filter 'tmp_*' -ErrorAction SilentlyContinue
$modified = @()
foreach ($f in $targets) {
  $path = $f.FullName
  $text = Get-Content $path -Raw
  $orig = $text
  $text = $text -replace 'SKP\s+D','SKPD'
  $text = $text -replace 'SKPDdan','SKPD dan'
  $text = $text -replace 'SKPD(?=[A-Za-z\/])','SKPD '
  if ($text -ne $orig) {
    $bak = $path + '.bak_' + (Get-Date -UFormat '%Y%m%d%H%M%S')
    Copy-Item -Path $path -Destination $bak -Force
    Set-Content -Path $path -Value $text -Encoding UTF8
    $modified += $path
    Write-Output ('Modified: {0}' -f $path)
  }
}
Write-Output ('Total modified: {0}' -f $modified.Count)
if ($modified.Count -gt 0) { $modified | ForEach-Object { Write-Output $_ } }
