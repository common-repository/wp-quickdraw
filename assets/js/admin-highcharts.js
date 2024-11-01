window.hifipixBatchImporter.gaugeOptions = {

    chart: {
        type: 'solidgauge',
        height: '97%',
        backgroundColor: 'transparent'
    },

    title: {
        style: {
            'fontFamily': "'Raleway', sans-serif",
            'fontSize': '36px',
            'fontWeight': 100
        }
    },

    pane: {
        center: ['50%', '70%'],
        size: '140%',
        startAngle: -90,
        endAngle: 90,
        background: {
            backgroundColor:
                Highcharts.defaultOptions.legend.backgroundColor || '#EEE',
            innerRadius: '60%',
            outerRadius: '100%',
            shape: 'arc'
        }
    },

    tooltip: {
        enabled: false
    },

    // the value axis
    yAxis: {
        stops: [
            [0.0, '#7e4396'], // purple
            [0.5, '#224397'], // blue
            [0.8, '#ef662f'] // orange
        ],
        lineWidth: 0,
        minorTickInterval: null,
        tickAmount: 2,
        title: {
            y: -70
        },
        labels: {
            y: 16,
            style: {
                'fontFamily': "'Raleway', sans-serif",
                'fontSize': '18px',
                'fontWeight': 200
            }
        }
    },

    plotOptions: {
        solidgauge: {
            dataLabels: {
                y: 5,
                borderWidth: 0,
                useHTML: true
            }
        }
    },

    responsive: {
        rules: [
        {
            condition: {  
                maxWidth: 245
            },  
            chartOptions: {  
                series: {
                    dataLabels: {
                        padding: 0,
                        crop: false,
                        overflow: 'allow'
                    }
                }
            }  
        },
        {
            condition: {  
                maxWidth: 238
            },  
            chartOptions: {  
                yAxis: {
                    labels: {
                        distance: -10
                    }
                }
            }  
        },
        {
            condition: {  
              maxWidth: 200
            },  
            chartOptions: {  
                pane: {
                    size: '100%'
                },
                title: {
                    style: {
                        'fontSize': '24px'
                    }
                }
            }  
        },
        {
          condition: {  
            maxWidth: 172
          },  
          chartOptions: {  
              pane: {
                  size: '140%'
              },
              yAxis: {
                labels: {
                    distance: -15
                }
              }
          }  
        },
        {
            condition: {  
              maxWidth: 124
            },  
            chartOptions: {  
                yAxis: {
                  labels: {
                      enabled: false
                  }
                }
            }  
          }
        ]
    }
};

// PNG gauge
window.hifipixBatchImporter.chartPng = Highcharts.chart('container-png', Highcharts.merge(
    window.hifipixBatchImporter.gaugeOptions, {
    title: {
        text: 'PNG'
    },

    yAxis: {
        min: 0,
        max: 100
    },

    credits: {
        enabled: false
    },

    series: [{
        name: 'PNG',
        data: [ window.wpqdHighcharts['image/png'].percent ],
        dataLabels: {
            format:
            '<div class="processed-container">' +
            '<span class="processed-value">{y}%</span><br/>' +
            '<span class="processed-label">WP QuickDraw<br />Enabled Files</span>' +
            '</div>'
        },
        tooltip: {
            valueSuffix: '%'
        }
    }]

}));

// JPG gauge
window.hifipixBatchImporter.chartJpg = Highcharts.chart('container-jpg', Highcharts.merge(
    window.hifipixBatchImporter.gaugeOptions, {
    title: {
        text: 'JPG'
    },

    yAxis: {
        min: 0,
        max: 100
    },

    credits: {
        enabled: false
    },

    series: [{
        name: 'JPG',
        data: [ window.wpqdHighcharts['image/jpeg'].percent ],
        dataLabels: {
            format:
            '<div class="processed-container">' +
            '<span class="processed-value">{y}%</span><br/>' +
            '<span class="processed-label">WP QuickDraw<br />Enabled Files</span>' +
            '</div>'
        },
        tooltip: {
            valueSuffix: '%'
        }
    }]

}));

// GIF gauge
window.hifipixBatchImporter.chartGif = Highcharts.chart('container-gif', Highcharts.merge(
    window.hifipixBatchImporter.gaugeOptions, {
    title: {
        text: 'GIF'
    },

    yAxis: {
        min: 0,
        max: 100
    },

    credits: {
        enabled: false
    },

    series: [{
        name: 'GIF',
        data: [ window.wpqdHighcharts['image/gif'].percent ],
        dataLabels: {
            format:
                '<div class="processed-container">' +
                '<span class="processed-value">{y}%</span><br/>' +
                '<span class="processed-label">WP QuickDraw<br />Enabled Files</span>' +
                '</div>'
        },
        tooltip: {
            valueSuffix: '%'
        }
    }]

}));

// Progress bar
window.hifipixBatchImporter.progressBar = new Highcharts.Chart({
    title: {
      text: 'Progress:',
      y: -9999
    },
    chart: {
      renderTo: 'wpqd-progress-bar',
      type: 'bar',
      height: 52,
      plotBorderWidth: 1,
      plotBorderColor: '#acacac',
      backgroundColor: 'transparent',
      margin: [1, 20, 1, 1],
      spacing: [0, 0, 0, 0]
    },
    credits: false,
    tooltip: false,
    legend: false,
    navigation: {
      buttonOptions: {
        enabled: false
      }
    },
    xAxis: {
      visible: false,
    },
    yAxis: {
      visible: false,
      min: 0,
      max: 100,
    },
    series: [{
      data: [100],
      grouping: false,
      animation: false,
      enableMouseTracking: false,
      showInLegend: false,
      color: 'transparent',
      pointWidth: 50,
      borderWidth: 0,
      borderColor: '#acacac',
    }, {
      enableMouseTracking: false,
      data: [ window.wpqdHighcharts['totals'].percent ],
      color: {
          pattern: {
              image: window.wpqdHighcharts.progressBg,
              aspectRatio: 1
          }
      },
      borderWidth: 0,
      pointWidth: 50,
      animation: {
        duration: 250,
      }
    }]
  });
