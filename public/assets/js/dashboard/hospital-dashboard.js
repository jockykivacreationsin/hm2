//*** hospital survay  start ***//
var options = {
  series: [
    {
      name: 'New Patients',
      data: [100, 480, 340, 690, 399, 250, 470, 580, 609, 488, 355]
    },
    {
      name: 'Recovered Patients',
      data: [150, 199, 280, 499, 620, 300, 540, 497, 398, 426, 300]
    }
  ],
  chart: {
    height: 380,
    type: 'area',
    parentHeightOffset: 0,
    parentWidthOffset: 0,
    toolbar: {
      show: false
    }
  },
  dataLabels: {
    enabled: false
  },
  markers: {
    size: [5, 5],
    strokeColors: '#fff',
    strokeWidth: 2,
    strokeOpacity: 0.4,
    fillOpacity: 1,
    hover: {
      sizeOffset: 1
    }
  },
  stroke: {
    curve: 'smooth',
    width: 2,
  },
  grid: {
    show: true
  },
  legend: {
    show: false,
  },
  colors: [Cdxmedixo.themeprimary, Cdxmedixo.themesecondary],
  yaxis: {
    labels: {
      offsetX: -15,
    },
  },
  xaxis: {
    categories: ["Jan", "Feb", "Mar", "Apr", "May", "Jun", "Jul", "Sept", "Oct", "Nov", "Dec"],
    labels: {
      offsetY: 5,
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
          height: 310
        }
      },
    }
  ]
};
var chart = new ApexCharts(document.querySelector("#hospitalsurvay"), options);
chart.render();
//*** hospital survay end ***//


//*** recoverystatistics start  ***/
var options = {
  series: [
    {
      name: 'Operation Failure',
      data: [40, 20, 30, 22, 35, 20, 15]
    },
    {
      name: 'Operation Success',
      data: [39, 29, 25, 29, 18, 22, 39]
    }
  ],
  chart: {
    height: 338,
    type: 'line',
    toolbar: {
      show: false
    }
  },
  dataLabels: {
    enabled: false,
  },
  grid: {
    strokeDashArray: 2,
  },
  stroke: {
    width: [4, 4],
    curve: 'smooth',
    dashArray: [6]
  },
  legend: {
    show: false,
  },
  colors: [Cdxmedixo.themeprimary, Cdxmedixo.themesecondary],
  yaxis: {
    labels: {
      formatter: function (y) {
        return y.toFixed(0) + "k";
      }
    },
    axisTicks: {
      show: false
    },
    axisBorder: {
      show: false
    },
    labels: {
      style: {
        colors: '#262626',
        fontSize: '14px',
        fontWeight: 500,
        fontFamily: 'Roboto, sans-serif'
      },
    },
  },
  xaxis: {
    categories: ["Jan", "Feb", "Mar", "Apr", "May", "Jun", "Jul", "Aug", "Sep", "Oct", "Nov", "Dec"],
    labels: {
      style: {
        colors: '#262626',
        fontSize: '14px',
        fontWeight: 500,
        fontFamily: 'Roboto, sans-serif'
      },
    },
    axisTicks: {
      show: false
    },
    axisBorder: {
      show: false
    }
  },
  responsive: [
    {
      breakpoint: 1440,
      options: {
        chart: {
          height: 340
        }
      },
    }
  ]
};
var chart = new ApexCharts(document.querySelector("#recoverystatistics"), options);
chart.render();
//**** recoverystatistics end  ***//


//*** Diseases Report start  ***//
var options = {
  series: [
    {
      name: 'Fever',
      data: [20, 30, 20, 30, 20, 20, 40]
    },
    {
      name: 'Injury',
      data: [10, 5, 15, 25, 30, 18, 10]
    },
    {
      name: 'Ortho',
      data: [15, 28, 14, 26, 33, 22, 20]
    }
  ],
  chart: {
    type: 'bar',
    stacked: true,
    height: 375,
    toolbar: {
      show: false
    }
  },
  plotOptions: {
    bar: {
      borderRadius: 5,
      horizontal: false,
      columnWidth: '40%',
      endingShape: 'rounded'
    },
  },
  legend: {
    show: false,
  },
  grid: {
    strokeDashArray: 2,
  },
  colors: [Cdxmedixo.themeprimary, Cdxmedixo.themesecondary, Cdxmedixo.themesuccess],
  dataLabels: {
    enabled: false
  },
  stroke: {
    show: true,
    width: 2,
    colors: ['transparent']
  },
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
  yaxis: {
    tickAmount: 10,
    min: 10,
    max: 100,
    labels: {
      style: {
        colors: '#262626',
        fontSize: '14px',
        fontWeight: 500,
        fontFamily: 'Roboto, sans-serif'
      },
    },
  },
  xaxis: {
    categories: ['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'],
    axisTicks: {
      show: false
    },
    axisBorder: {
      show: false
    },
    labels: {
      style: {
        colors: '#262626',
        fontSize: '14px',
        fontWeight: 500,
        fontFamily: 'Roboto, sans-serif'
      },
    },
  },
  fill: {
    opacity: 1
  },
  responsive: [{
    breakpoint: 1441,
    options: {
      chart: {
        height: 378
      }
    },
  }]
};

var chart = new ApexCharts(document.querySelector("#diseasesreport"), options);
chart.render();
//*** Diseases Report end ***//


//**Top Rated Doctor**//
$('.ratedoctor-slide').slick({
  infinite: true,
  slidesToShow: 4,
  slidesToScroll: 1,
  dots: true,
  arrows: false,
  autoplay: true,
  autoplaySpeed: 3000,
  fadeSpeed: 1000,
  responsive: [
    {
      breakpoint: 1600,
      settings: {
        slidesToShow: 3,
        slidesToScroll: 1,
      }
    },
    {
      breakpoint: 1400,
      settings: {
        slidesToShow: 2,
        slidesToScroll: 1,
      }
    }
  ]
});