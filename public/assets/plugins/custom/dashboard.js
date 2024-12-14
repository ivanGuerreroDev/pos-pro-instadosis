$(document).ready(function() {
    getYearlySubscriptions();
    bestPlanSubscribes();
})

$('.overview-year').on('change', function () {
    let year = $(this).val();
    bestPlanSubscribes(year);
});

$('.yearly-statistics').on('change', function () {
    let year = $(this).val();
    getYearlySubscriptions(year);
});

function getYearlySubscriptions(year = new Date().getFullYear()) {
    var url = $('#yearly-subscriptions-url').val();
    $.ajax({
        type: "GET",
        url: url += '?year=' + year,
        dataType: "json",
        success: function (res) {
            var subscriptions = [];

            for (var i = 0; i <= 11; i++) {
                var monthName = getMonthNameFromIndex(i); // Implement this function to get month name

                var subscriptionsData = res.find(item => item.month === monthName);
                subscriptions[i] = subscriptionsData ? subscriptionsData.total_amount : 0;
            }
            subscriptionChart(subscriptions);
        },
    });
}

let userOverView = false;

// Function to update the User Overview chart
function bestPlanSubscribes(year = new Date().getFullYear()) {
    if (userOverView) {
        userOverView.destroy();
    }

    Chart.defaults.datasets.doughnut.cutout = '65%';
    let url = $('#get-plans-overview').val();
    $.ajax({
        url: url += '?year=' + year,
        type: 'GET',
        dataType: 'json',
        success: function (res) {

            var labels = [];
            var data = [];

            $.each(res, function(index, planData) {
                var label = planData.plan.subscriptionName + ": " + planData.plan_count;
                labels.push(label);
                data.push(planData.plan_count);
            });

            var roundedCornersFor = {
                "start": Array.from({ length: data.length }, (_, i) => i)
            };
            Chart.defaults.elements.arc.roundedCornersFor = roundedCornersFor;

            let inMonths = $("#plans-chart");
            userOverView = new Chart(inMonths, {
                type: 'doughnut',
                data: {
                    labels: labels,
                    datasets: [{
                        label: "Total Users",
                        borderWidth: 0,
                        data: data,
                        backgroundColor: [
                            "#2CE78D",
                            "#0a7cc2",
                            "#C52127",
                            "#2DB0F6",
                        ],
                        borderColor: [
                            "#2CE78D",
                            "#0a7cc2",
                            "#2CE78D",
                            "#2DB0F6",
                        ],
                    }]
                },
                plugins: [{
                    afterUpdate: function (chart) {
                        if (chart.options.elements.arc.roundedCornersFor !== undefined) {
                            var arcValues = Object.values(chart.options.elements.arc.roundedCornersFor);

                            arcValues.forEach(function (arcs) {
                                arcs = Array.isArray(arcs) ? arcs : [arcs];
                                arcs.forEach(function (i) {
                                    var arc = chart.getDatasetMeta(0).data[i];
                                    arc.round = {
                                        x: (chart.chartArea.left + chart.chartArea
                                            .right) / 2,
                                        y: (chart.chartArea.top + chart.chartArea
                                            .bottom) / 2,
                                        radius: (arc.outerRadius + arc
                                            .innerRadius) / 2,
                                        thickness: (arc.outerRadius - arc
                                            .innerRadius) / 2,
                                        backgroundColor: arc.options.backgroundColor
                                    }
                                });
                            });
                        }
                    },
                    afterDraw: (chart) => {

                        if (chart.options.elements.arc.roundedCornersFor !== undefined) {
                            var {
                                ctx,
                                canvas
                            } = chart;
                            var arc,
                                roundedCornersFor = chart.options.elements.arc.roundedCornersFor;
                            for (var position in roundedCornersFor) {
                                var values = Array.isArray(roundedCornersFor[position]) ?
                                    roundedCornersFor[position] : [roundedCornersFor[position]];
                                values.forEach(p => {
                                    arc = chart.getDatasetMeta(0).data[p];
                                    var startAngle = Math.PI / 2 - arc.startAngle;
                                    var endAngle = Math.PI / 2 - arc.endAngle;
                                    ctx.save();
                                    ctx.translate(arc.round.x, arc.round.y);
                                    ctx.fillStyle = arc.options.backgroundColor;
                                    ctx.beginPath();
                                    if (position == "start") {
                                        ctx.arc(arc.round.radius * Math.sin(startAngle), arc
                                            .round.radius * Math.cos(startAngle), arc.round
                                            .thickness, 0, 2 * Math.PI);
                                    } else {
                                        ctx.arc(arc.round.radius * Math.sin(endAngle), arc.round
                                            .radius * Math.cos(endAngle), arc.round
                                            .thickness, 0, 2 * Math.PI);
                                    }
                                    ctx.closePath();
                                    ctx.fill();
                                    ctx.restore();
                                });

                            }
                            ;
                        }
                    }
                }],
                options: {
                    responsive: true,
                    tooltips: {
                        displayColors: true,
                        zIndex: 999999,
                    },
                    plugins: {
                        legend: {
                            position: 'top',
                            labels: {
                                usePointStyle: true,
                                padding: 10
                            }
                        }
                    },
                    scales: {
                        x: {
                            display: false,
                            stacked: true,
                        },
                        y: {
                            display: false,
                            stacked: true,
                        }
                    },
                }
            });
        },
        error: function (xhr, textStatus, errorThrown) {
            console.log('Error fetching user overview data: ' + textStatus);
        }
    });
}

// PRINT TOP DATA
getDashboardData();
function getDashboardData() {
    var url = $('#get-dashboard').val();
    $.ajax({
        type: "GET",
        url: url,
        dataType: "json",
        success: function (res) {
            $('#total_businesses').text(res.total_businesses);
            $('#expired_businesses').text(res.expired_businesses);
            $('#plan_subscribes').text(res.plan_subscribes);
            $('#business_categories').text(res.business_categories);
            $('#total_plans').text(res.total_plans);
            $('#total_staffs').text(res.total_staffs);
        }
    });
}

// Function to convert month index to month name
function getMonthNameFromIndex(index) {
    var months = [
        "January", "February", "March", "April", "May", "June",
        "July", "August", "September", "October", "November", "December"
    ];
    return months[index - 1];
}

let statiSticsValu = false;

function subscriptionChart(subscriptions) {
    if (statiSticsValu) {
        statiSticsValu.destroy();
    }

    var ctx = document.getElementById('monthly-statistics').getContext('2d');
    var gradient = ctx.createLinearGradient(0, 100, 10, 280);
    gradient.addColorStop(0, '#C52127');
    gradient.addColorStop(1, 'rgba(50, 130, 241, 0)');

        var totals = subscriptions.reduce(function (accumulator, currentValue) {

        return accumulator + currentValue;
    }, 0);

    statiSticsValu = new Chart(ctx, {
        type: 'line',
        data: {
            labels: ['Jan', 'Feb', 'March', 'April', 'May', 'June', 'July', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'],
            datasets: [{
                    backgroundColor: gradient,
                    label: "Total Subscription Amount: " + totals,
                    fill: true,
                    borderWidth: 1,
                    borderColor: "#C52127",
                    data: subscriptions,
                }
            ]
        },

        options: {
            responsive: true,
            tension: 0.3,
            tooltips: {
                displayColors: true,
            },
            plugins: {
                legend: {
                    position: 'top',
                    labels: {
                        usePointStyle: true,
                        padding: 30
                    }
                }
            },
            scales: {
                x: {
                    display: true,
                },
                y: {
                    display: true,
                    beginAtZero: true
                }
            },
        },
    });
};
