<?php $pageTitle = 'SAS - Analytics'; require_once __DIR__.'/db.php'; require_login('admin');
$k = max(2, min(8, (int)($_GET['k'] ?? 4)));
$stmt = $pdo->query('SELECT id, student_id, academic_gpa, academic_project, academic_certifications, academic_language, academic_attendance, cocurricular_events, cocurricular_innovation, cocurricular_membership, cocurricular_community, cocurricular_competitive, personality_leadership, personality_softskills, personality_feedback, personality_awards, total, grade, status FROM appraisals');
$allRows = $stmt->fetchAll();
$rows = array_values(array_filter($allRows, function($r){ return ($r['status'] ?? '') === 'approved'; }));
$usingAll = false; $usingDemo = false;
if (count($rows) === 0 && count($allRows) > 0) { $rows = $allRows; $usingAll = true; }
if (count($rows) === 0) {
  $usingDemo = true;
  $mk = function($id,$tot,$g,$parts){ return array_merge(['id'=>$id,'student_id'=>$id,'total'=>$tot,'grade'=>$g,'status'=>'approved'], $parts); };
  $rows = [
    $mk(1, 92, 'O',   ['academic_gpa'=>9,'academic_project'=>9,'academic_certifications'=>4,'academic_language'=>5,'academic_attendance'=>9,'cocurricular_events'=>12,'cocurricular_innovation'=>8,'cocurricular_membership'=>4,'cocurricular_community'=>4,'cocurricular_competitive'=>4,'personality_leadership'=>5,'personality_softskills'=>5,'personality_feedback'=>4,'personality_awards'=>4]),
    $mk(2, 83, 'A+',  ['academic_gpa'=>8,'academic_project'=>8,'academic_certifications'=>3,'academic_language'=>4,'academic_attendance'=>8,'cocurricular_events'=>10,'cocurricular_innovation'=>7,'cocurricular_membership'=>3,'cocurricular_community'=>3,'cocurricular_competitive'=>3,'personality_leadership'=>4,'personality_softskills'=>4,'personality_feedback'=>4,'personality_awards'=>2]),
    $mk(3, 71, 'A',   ['academic_gpa'=>7,'academic_project'=>7,'academic_certifications'=>2,'academic_language'=>3,'academic_attendance'=>7,'cocurricular_events'=>9,'cocurricular_innovation'=>6,'cocurricular_membership'=>3,'cocurricular_community'=>2,'cocurricular_competitive'=>2,'personality_leadership'=>3,'personality_softskills'=>3,'personality_feedback'=>3,'personality_awards'=>1]),
    $mk(4, 62, 'B+',  ['academic_gpa'=>6,'academic_project'=>6,'academic_certifications'=>2,'academic_language'=>3,'academic_attendance'=>6,'cocurricular_events'=>7,'cocurricular_innovation'=>5,'cocurricular_membership'=>2,'cocurricular_community'=>2,'cocurricular_competitive'=>2,'personality_leadership'=>3,'personality_softskills'=>3,'personality_feedback'=>3,'personality_awards'=>1]),
    $mk(5, 54, 'B',   ['academic_gpa'=>5,'academic_project'=>5,'academic_certifications'=>1,'academic_language'=>2,'academic_attendance'=>6,'cocurricular_events'=>6,'cocurricular_innovation'=>4,'cocurricular_membership'=>2,'cocurricular_community'=>2,'cocurricular_competitive'=>2,'personality_leadership'=>2,'personality_softskills'=>3,'personality_feedback'=>3,'personality_awards'=>1]),
    $mk(6, 45, 'C',   ['academic_gpa'=>4,'academic_project'=>4,'academic_certifications'=>1,'academic_language'=>2,'academic_attendance'=>5,'cocurricular_events'=>5,'cocurricular_innovation'=>3,'cocurricular_membership'=>1,'cocurricular_community'=>1,'cocurricular_competitive'=>1,'personality_leadership'=>2,'personality_softskills'=>2,'personality_feedback'=>2,'personality_awards'=>0])
  ];
}
$features = ['academic_gpa'=>10,'academic_project'=>10,'academic_certifications'=>5,'academic_language'=>5,'academic_attendance'=>10,'cocurricular_events'=>15,'cocurricular_innovation'=>10,'cocurricular_membership'=>5,'cocurricular_community'=>5,'cocurricular_competitive'=>5,'personality_leadership'=>5,'personality_softskills'=>5,'personality_feedback'=>5,'personality_awards'=>5];
if (!empty($_GET['export'])) {
  $exp = preg_replace('/[^a-zA-Z0-9_-]/','', $_GET['export']);
  if (ob_get_level()) { @ob_end_clean(); }
  header('Content-Type: text/csv; charset=UTF-8');
  header('Content-Disposition: attachment; filename="'.$exp.'.csv"');
  header('Pragma: no-cache');
  header('Expires: 0');
  echo "\xEF\xBB\xBF"; // UTF-8 BOM for Excel
  $out = fopen('php://output', 'w');
  if ($exp === 'appraisals') {
    fputcsv($out, array_merge(['id','student_id'], array_keys($features), ['total','grade']));
    foreach ($rows as $r) { $line = [$r['id'],$r['student_id']]; foreach(array_keys($features) as $f){ $line[] = (int)$r[$f]; } $line[]=(int)$r['total']; $line[]=$r['grade']; fputcsv($out, $line); }
  } else if ($exp === 'clusters') {
    $data = [];
    foreach ($rows as $r) { $v = []; foreach($features as $f=>$mx){ $v[] = ($mx>0?((int)$r[$f]/$mx):0.0); } $data[] = ['id'=>$r['id'], 'vec'=>$v]; }
    $kk = max(1, min($k, count($data)));
    $clusters = kmeans($data, $kk);
    fputcsv($out, ['appraisal_id','cluster']);
    foreach ($clusters['assign'] as $idx=>$cid) { fputcsv($out, [$data[$idx]['id'], $cid]); }
  } else if ($exp === 'recommendations') {
    $recs = recommendations($rows, $features);
    fputcsv($out, ['appraisal_id','total','grade','suggestion']);
    foreach ($recs as $rec) { fputcsv($out, [$rec['id'],$rec['total'],$rec['grade'],$rec['suggestion']]); }
  }
  fclose($out); exit;
}
function mean($arr){ $n=count($arr); if(!$n) return 0; return array_sum($arr)/$n; }
function stddev($arr){ $n=count($arr); if($n<2) return 0; $m=mean($arr); $v=0; foreach($arr as $x){ $v+=($x-$m)*($x-$m); } return sqrt($v/($n-1)); }
function corr($x,$y){ $n=count($x); if($n<2) return 0; $mx=mean($x); $my=mean($y); $sx=stddev($x); $sy=stddev($y); if($sx==0||$sy==0) return 0; $s=0; for($i=0;$i<$n;$i++){ $s+=($x[$i]-$mx)*($y[$i]-$my); } return $s/(($n-1)*$sx*$sy); }
function kmeans($data,$k){ $n=count($data); if($n==0){ return ['assign'=>[], 'centers'=>[]]; }
  if($k<1){ $k=1; }
  if($k>$n){ $k=$n; }
  $dim = count($data[0]['vec']);
  $centers = [];
  $used = [];
  for($i=0;$i<$k;$i++){ do{$idx=rand(0,$n-1);}while(isset($used[$idx])); $used[$idx]=1; $centers[] = $data[$idx]['vec']; }
  $assign = array_fill(0,$n,0);
  for($iter=0;$iter<50;$iter++){
    $changed=false;
    for($i=0;$i<$n;$i++){
      $best=0; $bestd=INF;
      for($c=0;$c<$k;$c++){
        $d=0; for($d_i=0;$d_i<$dim;$d_i++){ $diff=$data[$i]['vec'][$d_i]-$centers[$c][$d_i]; $d+=$diff*$diff; }
        if($d<$bestd){ $bestd=$d; $best=$c; }
      }
      if($assign[$i]!==$best){ $assign[$i]=$best; $changed=true; }
    }
    $newC=array_fill(0,$k,array_fill(0,$dim,0.0)); $cnt=array_fill(0,$k,0);
    for($i=0;$i<$n;$i++){ $c=$assign[$i]; $cnt[$c]++; for($d_i=0;$d_i<$dim;$d_i++){ $newC[$c][$d_i]+=$data[$i]['vec'][$d_i]; } }
    for($c=0;$c<$k;$c++){ if($cnt[$c]>0){ for($d_i=0;$d_i<$dim;$d_i++){ $newC[$c][$d_i]/=$cnt[$c]; } } else { $newC[$c]=$centers[$c]; }
    }
    $centers=$newC; if(!$changed) break;
  }
  return ['assign'=>$assign,'centers'=>$centers];
}
function recommendations($rows,$features){
  $thresholds = [
    ['grade'=>'C','min'=>0],
    ['grade'=>'B','min'=>50],
    ['grade'=>'B+','min'=>60],
    ['grade'=>'A','min'=>70],
    ['grade'=>'A+','min'=>80],
    ['grade'=>'O','min'=>90]
  ];
  $out=[]; foreach($rows as $r){
    $total=(int)$r['total'];
    $next=null; for($i=0;$i<count($thresholds);$i++){ if($total < $thresholds[$i]['min']) { $next=$thresholds[$i]; break; } }
    if($total>=90){ $out[]=['id'=>$r['id'],'total'=>$total,'grade'=>$r['grade'],'suggestion'=>'Maintain']; continue; }
    $needed = ($next?max(0,$next['min']-$total):0);
    $gaps=[]; foreach($features as $f=>$mx){ $gaps[]=['k'=>$f,'gap'=>max(0,$mx-(int)$r[$f]),'mx'=>$mx]; }
    usort($gaps,function($a,$b){ return $b['gap']<=>$a['gap']; });
    $top=array_slice($gaps,0,2);
    $suggParts=[]; foreach($top as $t){ $suggParts[] = strtoupper(str_replace('_',' ', $t['k'])); }
    $sugg = (count($suggParts)?implode(' and ',$suggParts):'Focus areas').' (+' . $needed . ' total)';
    $out[]=['id'=>$r['id'],'total'=>$total,'grade'=>$r['grade'],'suggestion'=>$sugg];
  }
  usort($out,function($a,$b){ return $a['total']<=>$b['total']; });
  return array_slice($out,0,30);
}
$totals = array_map(function($r){ return (int)$r['total']; }, $rows);
$grades = [];
foreach ($rows as $r){ $g=$r['grade']?:'N/A'; $grades[$g]=($grades[$g]??0)+1; }
$means = [];
foreach(array_keys($features) as $f){ $means[$f] = mean(array_map(function($r) use ($f){ return (int)$r[$f]; }, $rows)); }
$correls = [];
foreach(array_keys($features) as $f){ $x = array_map(function($r) use ($f){ return (int)$r[$f]; }, $rows); $correls[$f] = count($rows)>=2 ? corr($x,$totals) : 0; }
$data = [];
foreach ($rows as $r) { $v=[]; foreach($features as $f=>$mx){ $v[] = $mx>0?((int)$r[$f]/$mx):0.0; } $data[]=['id'=>$r['id'],'vec'=>$v,'total'=>(int)$r['total']]; }
$clusters = count($rows)>=$k ? kmeans($data,$k) : ['assign'=>[], 'centers'=>[]];
$clusterCounts = array_fill(0,$k,0);
foreach ($clusters['assign'] as $cid){ if(isset($clusterCounts[$cid])) $clusterCounts[$cid]++; }
$recs = recommendations($rows,$features);
include __DIR__.'/includes/header.php'; ?>
<div class="d-flex justify-content-between align-items-center mb-3">
  <h5 class="mb-0">Analytics</h5>
  <div class="d-flex gap-2 align-items-center">
    <form method="get" class="d-flex align-items-center gap-2">
      <label class="form-label mb-0">Clusters</label>
      <select class="form-select form-select-sm" name="k" onchange="this.form.submit()">
        <?php for($i=2;$i<=8;$i++): ?>
          <option value="<?php echo $i; ?>" <?php echo $i===$k?'selected':''; ?>>k=<?php echo $i; ?></option>
        <?php endfor; ?>
      </select>
    </form>
    <div class="btn-group">
      <a class="btn btn-sm btn-outline-secondary" href="?export=appraisals">Export Appraisals CSV</a>
      <a class="btn btn-sm btn-outline-secondary" href="?export=clusters&k=<?php echo $k; ?>">Export Clusters CSV</a>
      <a class="btn btn-sm btn-outline-secondary" href="?export=recommendations">Export Recommendations CSV</a>
    </div>
  </div>
</div>
<?php if ($usingDemo): ?>
  <div class="alert alert-info">No data yet. Showing demo analytics for illustration. Add appraisals to see real analytics.</div>
<?php elseif ($usingAll): ?>
  <div class="alert alert-warning">No approved appraisals yet. Showing analytics on all appraisals (draft/submitted included).</div>
<?php endif; ?>

<div class="card shadow-sm mb-3">
  <div class="card-header bg-white fw-bold">How to read these analytics</div>
  <div class="card-body small">
    <div><strong>Totals Distribution:</strong> number of students per total-score bucket (0–100).</div>
    <div><strong>Feature Means vs Max:</strong> average of each component; bars show mean vs maximum available points.</div>
    <div><strong>Correlation with Total (Pearson r):</strong> r = cov(X,Total) / (sd(X)·sd(Total)). Values near 1 mean strong positive relation.</div>
    <div><strong>K-Means Clusters:</strong> students grouped by normalized features x_i/max_i, optimizing within-cluster distance.</div>
    <div><strong>Recommendations:</strong> categories with the largest remaining points to reach the next grade threshold.</div>
  </div>
</div>
<div class="row g-3">
  <div class="col-lg-6">
    <div class="card shadow-sm">
      <div class="card-header bg-white fw-bold">Totals Distribution</div>
      <div class="card-body"><canvas id="totalsChart" height="140"></canvas></div>
    </div>
  </div>
  <div class="col-lg-6">
    <div class="card shadow-sm">
      <div class="card-header bg-white fw-bold">Grades</div>
      <div class="card-body"><canvas id="gradesChart" height="140"></canvas></div>
    </div>
  </div>
  <div class="col-12">
    <div class="card shadow-sm">
      <div class="card-header bg-white fw-bold">Feature Means vs Max</div>
      <div class="card-body"><canvas id="meansChart" height="160"></canvas></div>
    </div>
  </div>
  <div class="col-12">
    <div class="card shadow-sm">
      <div class="card-header bg-white fw-bold">Feature-Total Correlation</div>
      <div class="card-body"><canvas id="corrChart" height="160"></canvas></div>
    </div>
  </div>
  <div class="col-12">
    <div class="card shadow-sm">
      <div class="card-header bg-white fw-bold d-flex justify-content-between align-items-center"><span>Clusters</span><small class="text-muted">k=<?php echo $k; ?></small></div>
      <div class="card-body"><canvas id="clusterChart" height="140"></canvas></div>
    </div>
  </div>
  <div class="col-12">
    <div class="card shadow-sm">
      <div class="card-header bg-white fw-bold">Recommendations (Next Grade)</div>
      <div class="card-body table-responsive">
        <table class="table table-striped align-middle mb-0">
          <thead><tr><th>Appraisal</th><th>Total</th><th>Grade</th><th>Suggestion</th></tr></thead>
          <tbody>
            <?php foreach($recs as $r): ?>
              <tr>
                <td>#<?php echo (int)$r['id']; ?></td>
                <td><?php echo (int)$r['total']; ?></td>
                <td><?php echo htmlspecialchars($r['grade']); ?></td>
                <td><?php echo htmlspecialchars($r['suggestion']); ?></td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>
</div>
<script>
  window.addEventListener('load', function(){
    const totals = <?php echo json_encode($totals, JSON_HEX_TAG|JSON_HEX_APOS|JSON_HEX_AMP|JSON_HEX_QUOT); ?>;
    const gradeLabels = <?php echo json_encode(array_keys($grades), JSON_HEX_TAG|JSON_HEX_APOS|JSON_HEX_AMP|JSON_HEX_QUOT); ?>;
    const gradeCounts = <?php echo json_encode(array_values($grades), JSON_HEX_TAG|JSON_HEX_APOS|JSON_HEX_AMP|JSON_HEX_QUOT); ?>;
    const featureNames = <?php echo json_encode(array_map(function($k){ return str_replace('_',' ',ucfirst($k)); }, array_keys($features)), JSON_HEX_TAG|JSON_HEX_APOS|JSON_HEX_AMP|JSON_HEX_QUOT); ?>;
    const featureMeans = <?php echo json_encode(array_values($means), JSON_HEX_TAG|JSON_HEX_APOS|JSON_HEX_AMP|JSON_HEX_QUOT); ?>;
    const featureMax = <?php echo json_encode(array_values($features), JSON_HEX_TAG|JSON_HEX_APOS|JSON_HEX_AMP|JSON_HEX_QUOT); ?>;
    const corrVals = <?php echo json_encode(array_values($correls), JSON_HEX_TAG|JSON_HEX_APOS|JSON_HEX_AMP|JSON_HEX_QUOT); ?>;
    const clusterCounts = <?php echo json_encode(array_values($clusterCounts), JSON_HEX_TAG|JSON_HEX_APOS|JSON_HEX_AMP|JSON_HEX_QUOT); ?>;
    const colors = ['#0d6efd','#6f42c1','#198754','#dc3545','#fd7e14','#20c997','#6610f2','#0dcaf0'];
    function hist(arr, bins){ if(arr.length===0) return {labels:[],counts:[]}; const min=0, max=100; const step=(max-min)/bins; const counts=new Array(bins).fill(0); for(const v of arr){ const idx=Math.min(bins-1, Math.max(0, Math.floor((v-min)/step))); counts[idx]++; } const labels=[]; for(let i=0;i<bins;i++){ labels.push(`${Math.round(min+i*step)}-${Math.round(min+(i+1)*step)}`);} return {labels,counts}; }
    const t = hist(totals, 10);
    new Chart(document.getElementById('totalsChart'), { type:'bar', data:{ labels:t.labels, datasets:[{label:'Count', data:t.counts, backgroundColor:'#0d6efd'}] }, options:{ plugins:{legend:{display:false}} } });
    new Chart(document.getElementById('gradesChart'), { type:'pie', data:{ labels:gradeLabels, datasets:[{ data:gradeCounts, backgroundColor:gradeLabels.map((_,i)=>colors[i%colors.length]) }] } });
    new Chart(document.getElementById('meansChart'), { type:'bar', data:{ labels:featureNames, datasets:[{ label:'Mean', data:featureMeans, backgroundColor:'#198754' }, { label:'Max', data:featureMax, backgroundColor:'rgba(108,117,125,.35)' }] }, options:{ responsive:true, scales:{ x:{ ticks:{ autoSkip:false } } } } });
    new Chart(document.getElementById('corrChart'), { type:'bar', data:{ labels:featureNames, datasets:[{ label:'Correlation vs Total', data:corrVals, backgroundColor:'#6f42c1' }] }, options:{ scales:{ y:{ min:-1, max:1 } } } });
    new Chart(document.getElementById('clusterChart'), { type:'bar', data:{ labels:[...Array(<?php echo $k; ?>).keys()].map(i=>`C${i}`), datasets:[{ label:'Members', data:clusterCounts, backgroundColor:'#fd7e14' }] }, options:{ plugins:{legend:{display:false}} } });
  });
</script>
<?php include __DIR__.'/includes/footer.php'; ?>
