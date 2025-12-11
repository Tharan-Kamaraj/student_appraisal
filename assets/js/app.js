(function(){
  const form = document.getElementById('appraisalForm');
  if (form) {
    const totalEl = document.getElementById('total');
    const inputs = form.querySelectorAll('input.score');
    const recalc = () => {
      let t = 0; inputs.forEach(i => { const v = parseInt(i.value||'0',10); t += isNaN(v)?0:v; });
      totalEl.value = t;
      updateChart();
    };
    inputs.forEach(i => i.addEventListener('input', recalc));
    recalc();
    window.prefill = (data) => {
      document.getElementById('editId').value = data.id;
      inputs.forEach(i => { if (data[i.name] !== undefined) i.value = data[i.name]; });
      recalc();
      window.scrollTo({top:0, behavior:'smooth'});
    };

    // Doughnut chart for category breakdown
    let chart;
    const ctx = document.getElementById('scoreChart');
    function updateChart(){
      if (!window.Chart || !ctx) return;
      const vals = {
        academic: (['academic_gpa','academic_project','academic_certifications','academic_language','academic_attendance'].map(n=>parseInt(form[n]?.value||0,10)).reduce((a,b)=>a+b,0)) || 0,
        cocurricular: (['cocurricular_events','cocurricular_innovation','cocurricular_membership','cocurricular_community','cocurricular_competitive'].map(n=>parseInt(form[n]?.value||0,10)).reduce((a,b)=>a+b,0)) || 0,
        personality: (['personality_leadership','personality_softskills','personality_feedback','personality_awards'].map(n=>parseInt(form[n]?.value||0,10)).reduce((a,b)=>a+b,0)) || 0,
      };
      const data = {
        labels: ['Academic (40)','Co/Extra (40)','Personality (20)'],
        datasets: [{
          data: [vals.academic, vals.cocurricular, vals.personality],
          backgroundColor: ['#0d6efd','#20c997','#ffc107'],
          borderWidth: 0
        }]
      };
      if (!chart) {
        chart = new Chart(ctx, { type: 'doughnut', data, options: { plugins: { legend: { position: 'bottom' } }, cutout: '60%' } });
      } else {
        chart.data = data; chart.update();
      }
    }
    updateChart();
  }

  // Export Excel for My Appraisals table
  const exportBtn = document.getElementById('exportExcel');
  if (exportBtn) {
    exportBtn.addEventListener('click', () => {
      const table = document.getElementById('myAppraisalsTable');
      if (!table || !window.XLSX) return;
      const wb = XLSX.utils.table_to_book(table, {sheet: 'Appraisals'});
      XLSX.writeFile(wb, 'my_appraisals.xlsx');
    });
  }

  // Admin leaderboard bar chart
  if (window.adminLeaderboard) {
    const c = document.getElementById('leaderboardChart');
    if (c && window.Chart) {
      const labels = window.adminLeaderboard.map(r => r.name);
      const totals = window.adminLeaderboard.map(r => r.total);
      new Chart(c, {
        type: 'bar',
        data: { labels, datasets: [{ label: 'Total', data: totals, backgroundColor: '#0d6efd' }] },
        options: { plugins: { legend: { display: false } }, scales: { y: { beginAtZero: true, max: 100 } } }
      });
    }
  }
})();
