<?php
// components/charts/chart.php
function renderChart($id, $type, $labels = [], $datasets = [], $options = [])
{
  $jsonLabels = json_encode($labels ?: []);
  $jsonDatasets = json_encode($datasets ?: []);
  $jsonOptions = json_encode($options ?: new stdClass()); // empty object if none

  echo "
  <canvas id='$id' style='height:300px; width:100%;'></canvas>
  <script>
    window.addEventListener('load', function () {
      var ctx = document.getElementById('$id');
      if (ctx) {
        new Chart(ctx, {
          type: '$type',
          data: {
            labels: $jsonLabels,
            datasets: $jsonDatasets
          },
          options: $jsonOptions
        });
      }
    });
  </script>
  ";
}
