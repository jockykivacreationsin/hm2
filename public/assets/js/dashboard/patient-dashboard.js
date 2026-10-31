//*** blood level start ***//
var options = {
  series: [
    {
      name: 'Activities',
      type: 'bar',
      data: [80, 65, 50, 100, 85, 60, 75],
    }
  ],

  chart: {
    height: 375,
    type: 'line',
    parentHeightOffset: 0,
    parentWidthOffset: 0,
    toolbar: {
      show: false
    },
    zoom: {
      enabled: false
    }
  },
  legend: {
    show: false
  },
  dataLabels: {
    enabled: false
  },
  stroke: {
    width: [2],
    curve: 'smooth',
  },
  plotOptions: {
    bar: {
      columnWidth: "20%",
      startingShape: "rounded",
      endingShape: "rounded",
    }
  },
  fill: {
    opacity: [1, 0.08],
    gradient: {
      type: "horizontal",
      opacityFrom: 0.5,
      opacityTo: 0.1,
      stops: [100, 100, 100]
    }
  },
  colors: [Cdxmedixo.themeprimary],
  states: {
    normal: {
      filter: {
        type: 'darken',
        value: 1,
      }
    },
    hover: {
      filter: {
        type: 'darken',
        value: 1,
      }
    },
    active: {
      allowMultipleDataPointsSelection: false,
      filter: {
        type: 'darken',
        value: 1,
      }
    },
  },
  grid: {
    strokeDashArray: 2,
    padding: {
      top: 0,
      bottom: 0,
      left: 0,
      right: 0
    }
  },
  yaxis: {
    labels: {
      offsetX: -15,
      formatter: function (y) {
        return "" + y.toFixed(0);
      },
      style: {
        colors: '#262626',
        fontSize: '14px',
        fontWeight: 500,
        fontFamily: 'Poppins, sans-serif'
      }
    },
  },
  xaxis: {
    categories: ["Eating", "Drinking", "Sleeping", "Designing", "Coding", "Cycling", "Running"],
    axisTicks: {
      show: false
    },
    axisBorder: {
      show: false
    },
    labels: {
      offsetY: 5,
      style: {
        colors: '#262626',
        fontSize: '14px',
        fontWeight: 500,
        fontFamily: 'Poppins, sans-serif'
      },
    },
  },
  responsive: [
    {
      breakpoint: 1441,
      options: {
        chart: {
          height: 445
        }
      },
    },
    {
      breakpoint: 1366,
      options: {
        chart: {
          height: 320
        }
      },
    },
  ]
};
var chart = new ApexCharts(document.querySelector("#bloodlevel"), options);
chart.render();
//*** blood level end  ***//

//*** blood report start ***//
var options = {
  series: [{
    name: 'blood ',
    data: [30, 25, 28, 20, 22, 20, 25, 20, 18, 26, 22]
  }],
  chart: {
    height: 200,
    type: 'area',
    toolbar: {
      show: false
    }
  },
  dataLabels: {
    enabled: false
  },
  stroke: {
    curve: 'smooth'
  },
  grid: {
    show: false
  },
  colors: [Cdxmedixo.themeprimary],
  yaxis: {
    labels: {
      show: false
    },
  },
  xaxis: {
    labels: {
      show: false
    },
    axisTicks: {
      show: false
    },
    axisBorder: {
      show: false
    },
  },
  responsive: [
    {
      breakpoint: 1441,
      options: {
        chart: {
          height: 120
        }
      },
    },
  ]
};

var chart = new ApexCharts(document.querySelector("#bloodpreport"), options);
chart.render();
//*** blood report end ***//

//*** heart report start ***//
var options = {
  series: [{
    name: 'blood ',
    data: [30, 25, 28, 20, 22, 20, 25, 20, 18, 26, 22]
  }],
  chart: {
    height: 200,
    type: 'area',
    toolbar: {
      show: false
    }
  },
  dataLabels: {
    enabled: false
  },
  stroke: {
    curve: 'smooth'
  },
  grid: {
    show: false
  },
  colors: [Cdxmedixo.themesecondary],
  yaxis: {
    labels: {
      show: false
    },
  },
  xaxis: {
    labels: {
      show: false
    },
    axisTicks: {
      show: false
    },
    axisBorder: {
      show: false
    },
  },
  responsive: [
    {
      breakpoint: 1441,
      options: {
        chart: {
          height: 120
        }
      },
    },
  ]
};

var chart = new ApexCharts(document.querySelector("#heartrate"), options);
chart.render();
//*** heart report end ***//

//*** glucos report start ***//
var options = {
  series: [{
    name: 'blood ',
    data: [30, 25, 28, 20, 22, 20, 25, 20, 18, 26, 22]
  }],
  chart: {
    height: 200,
    type: 'area',
    toolbar: {
      show: false
    }
  },
  dataLabels: {
    enabled: false
  },
  stroke: {
    curve: 'smooth'
  },
  grid: {
    show: false
  },
  colors: [Cdxmedixo.themesuccess],
  yaxis: {
    labels: {
      show: false
    },
  },
  xaxis: {
    labels: {
      show: false
    },
    axisTicks: {
      show: false
    },
    axisBorder: {
      show: false
    },
  },
  responsive: [
    {
      breakpoint: 1441,
      options: {
        chart: {
          height: 120
        }
      },
    },
  ]
};
var chart = new ApexCharts(document.querySelector("#glucoserate"), options);
chart.render();
//*** glucos report end ***//

//*** Clolesterol report start ***//
var options = {
  series: [{
    name: 'blood ',
    data: [30, 25, 28, 20, 22, 20, 25, 20, 18, 26, 22]
  }],
  chart: {
    height: 200,
    type: 'area',
    toolbar: {
      show: false
    }
  },
  dataLabels: {
    enabled: false
  },
  stroke: {
    curve: 'smooth'
  },
  grid: {
    show: false
  },
  colors: [Cdxmedixo.themewarning],
  yaxis: {
    labels: {
      show: false
    },
  },
  xaxis: {
    labels: {
      show: false
    },
    axisTicks: {
      show: false
    },
    axisBorder: {
      show: false
    },
  },
  responsive: [
    {
      breakpoint: 1441,
      options: {
        chart: {
          height: 120
        }
      },
    },
  ]
};

var chart = new ApexCharts(document.querySelector("#clolesterol"), options);
chart.render();
//*** Clolesterol report end ***//