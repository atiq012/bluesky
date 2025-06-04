<!DOCTYPE html>

<html lang="en" data-bs-theme="">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>B2B | BlueSky | NDC</title>
    <meta http-equiv="Content-Security-Policy" content="upgrade-insecure-requests">
    <meta name="api-base-url" content="{{ url('') }}" />
    <link rel="icon" href="{{ url('/theme/appimages/blueskywings.png') }}">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/select2/4.0.13/css/select2.min.css" rel="stylesheet" />

    <link rel="stylesheet" type="text/css" href="https://cdn.jsdelivr.net/npm/daterangepicker/daterangepicker.css" />
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>

<body class="hold-transition sidebar-mini">
    <div class="wrapper" id="app">

    </div>
    <script src="https://code.jquery.com/jquery-3.7.1.min.js"
        integrity="sha256-/JqT3SQfawRcv/BIHPThkBvs0OEvtFFmqPF/lYI/Cxo=" crossorigin="anonymous"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/select2/4.0.13/js/select2.min.js"></script>
    <script type="text/javascript" src="https://cdn.jsdelivr.net/npm/daterangepicker/daterangepicker.min.js"></script>
    <script>
        let allValue = JSON.parse(localStorage.getItem('piniaUserAuth'));
        if(allValue != null){

            if (allValue.isDarkMode) {
                $("html").attr("data-bs-theme", 'dark')
            } else {
                $("html").attr("data-bs-theme", 'light')
            }
        }

    </script>

</body>

</html>
