{{-- Inlined rather than linked: Browsershot renders a standalone HTML string
     with no server to fetch a <script src> from, so the library has to be
     embedded directly. Doesn't affect the final PDF's file size - this is
     only part of the transient page Chrome renders, not the output. --}}
<script>{!! file_get_contents(base_path('node_modules/apexcharts/dist/apexcharts.min.js')) !!}</script>
<script>window.__pdfCharts = [];</script>
