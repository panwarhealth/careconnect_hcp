$ErrorActionPreference = "Stop"
$base = "$env:LOCALAPPDATA\Temp\fy28"
$manifest = Get-Content "$base\manifest.json" -Raw -Encoding UTF8 | ConvertFrom-Json
$dest = "$base\final.pptx"
Copy-Item "$base\jas.pptx" $dest -Force
$pp = New-Object -ComObject PowerPoint.Application
$p = $pp.Presentations.Open($dest, $false, $false, $false)
while ($p.Slides.Count -gt 0) { $p.Slides.Item(1).Delete() }
function Replace-In($shape, $old, $new) {
  $n = 0
  if ($shape.Type -eq 6) { foreach ($g in $shape.GroupItems) { $n += Replace-In $g $old $new }; return $n }
  if ($shape.HasTable) {
    foreach ($row in $shape.Table.Rows) { foreach ($c in $row.Cells) { if ($c.Shape.TextFrame.TextRange.Replace($old, $new)) { $n++ } } }
    return $n
  }
  if ($shape.HasTextFrame) { if ($shape.TextFrame.TextRange.Replace($old, $new)) { $n++ } }
  return $n
}
$srcs = @{}
foreach ($m in $manifest) {
  $src = "$base\$($m.f).pptx"
  if (-not $srcs.ContainsKey($m.f)) { $srcs[$m.f] = $pp.Presentations.Open($src, $true, $false, $false) }
  $ss = $srcs[$m.f].Slides.Item([int]$m.i)
  $dName = $ss.Design.Name; $lName = $ss.CustomLayout.Name
  [void]$p.Slides.InsertFromFile($src, $p.Slides.Count, $m.i, $m.i)
  $s = $p.Slides.Item($p.Slides.Count)
  $found = $null
  foreach ($d in $p.Designs) { if ($d.Name -eq $dName) { foreach ($l in $d.SlideMaster.CustomLayouts) { if ($l.Name -eq $lName -and -not $found) { $found = $l } } } }
  if ($found) { $s.CustomLayout = $found } else { Write-Output "NO LAYOUT slide $($p.Slides.Count): $dName | $lName" }
  foreach ($r in $m.r) {
    $hits = 0
    foreach ($sh in $s.Shapes) { $hits += Replace-In $sh $r[0] $r[1] }
    if ($hits -eq 0) { Write-Output "NO MATCH slide $($p.Slides.Count): $($r[0])" }
  }
  foreach ($bt in $m.b) {
    foreach ($sh in $s.Shapes) { if ($sh.HasTextFrame) { $f = $sh.TextFrame.TextRange.Find($bt); if ($f) { $f.Font.Bold = 1 } } }
  }
  if ($m.tag) {
    $W = $p.PageSetup.SlideWidth
    $g = $s.Shapes.AddShape(5, $W - 30 - 260, 8, 260, 20)
    $g.Name = "Period tag"
    $g.Adjustments.Item(1) = 0.5
    $g.Fill.ForeColor.RGB = 0xFAF0E8
    $g.Line.Visible = 0
    $g.Shadow.Visible = 0
    $g.TextFrame.MarginTop = 0; $g.TextFrame.MarginBottom = 0
    $gt = $g.TextFrame.TextRange
    $gt.Text = $m.tag; $gt.Font.Name = "Arial"; $gt.Font.Size = 9; $gt.Font.Bold = 1; $gt.Font.Color.RGB = 0x6B3615
  }
  if ($m.badge) {
    $W = $p.PageSetup.SlideWidth
    $b = $s.Shapes.AddShape(5, $W - 30 - 230, 18, 230, 30)
    $b.Name = "Hours badge"
    $b.Adjustments.Item(1) = 0.5
    $b.Fill.ForeColor.RGB = 0x6B7216
    $b.Line.Visible = 0
    $b.Shadow.Visible = 0
    $t = $b.TextFrame.TextRange
    $t.Text = $m.badge[0]; $t.Font.Name = "Arial"; $t.Font.Size = 13; $t.Font.Bold = 1; $t.Font.Color.RGB = 0xFFFFFF
    $n = $s.Shapes.AddTextbox(1, $W - 30 - 300, 50, 300, 20)
    $n.Name = "Hours note"
    $nt = $n.TextFrame.TextRange
    $nt.Text = $m.badge[1]; $nt.Font.Name = "Arial"; $nt.Font.Size = 10; $nt.Font.Italic = 1; $nt.Font.Color.RGB = 0x80726B
    $nt.ParagraphFormat.Alignment = 3
  }
}
foreach ($k in $srcs.Keys) { $srcs[$k].Close() }
$p.Save()
New-Item -ItemType Directory -Force "$base\png_final" | Out-Null
Get-ChildItem "$base\png_final" | Remove-Item
foreach ($s in $p.Slides) { $s.Export("$base\png_final\s$($s.SlideIndex).png", "PNG", 1600, 900) }
"slides: " + $p.Slides.Count
$p.Close()
