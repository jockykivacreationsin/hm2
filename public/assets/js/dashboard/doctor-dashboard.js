// doctor skill chart
var options = {
  series: [44, 55, 30, 43],
  chart: {
  height: 410,
  type: 'pie',
},
labels: ['Operations', 'Endoscopic', 'Patient Care', 'Patient Visit'],
legend: {
  position: 'bottom'
},
colors: [Cdxmedixo.themeprimary,Cdxmedixo.themesecondary,Cdxmedixo.themeinfo,Cdxmedixo.themewarning],
responsive: [
  {
    breakpoint: 1400,
    options: {
      chart: {
        height: 280,
      },
    }
  }
]
};
var chart = new ApexCharts(document.querySelector("#doctskill"), options);
chart.render();
// doctor skill end