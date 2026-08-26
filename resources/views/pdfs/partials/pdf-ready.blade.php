{{-- Signals GenerateDashboardReport's waitUntilReady() (which polls for
     window.pdfReady === true) once every chart has finished its async
     render - without this the PDF would be captured mid-render, showing
     blank chart containers. Resolves pdfReady even on a chart error so a
     single bad dataset can't hang the whole render until the 30s timeout. --}}
<script>
    Promise.all(window.__pdfCharts)
        .then(function () { window.pdfReady = true; })
        .catch(function () { window.pdfReady = true; });
</script>
