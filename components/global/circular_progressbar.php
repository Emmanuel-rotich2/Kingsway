<!-- filepath: /home/opt/lampp/htdocs/Kingsway/components/global/circular_progressbar.php -->
<div class="d-flex justify-content-center align-items-center" style="height:100px;">
  <div class="circular-loader">
    <svg class="circular" viewBox="25 25 50 50">
      <circle class="path" cx="50" cy="50" r="20" fill="none" stroke-width="5"/>
    </svg>
  </div>
</div>
<style>
.circular-loader {
  display: inline-block;
  width: 60px;
  height: 60px;
}
.circular {
  animation: rotate 2s linear infinite;
  width: 60px;
  height: 60px;
}
.path {
  stroke: #28a745;
  stroke-linecap: round;
  animation: dash 1.5s ease-in-out infinite;
}
@keyframes rotate {
  100% { transform: rotate(360deg);}
}
@keyframes dash {
  0% { stroke-dasharray: 1, 150; stroke-dashoffset: 0;}
  50% { stroke-dasharray: 90, 150; stroke-dashoffset: -35;}
  100% { stroke-dasharray: 90, 150; stroke-dashoffset: -124;}
}
</style>