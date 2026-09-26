<!doctype html>
<html lang="ar" dir="rtl">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <meta name="referrer" content="no-referrer">
  <title>تعذر إكمال الطلب — Mart.ps</title>
  <link rel="stylesheet" href="{{ asset('assets/error.css') }}">
</head>
<body class="error-page error-page--warning">
  <main class="card">
    <p class="code" aria-hidden="true">500</p>
    <h1>تعذر إكمال الطلب</h1>
    <p class="message">حدث خطأ غير متوقع. حاول مجددًا بعد قليل.</p>
    <a class="primary" href="{{ route('home') }}">العودة إلى الصفحة الرئيسية</a>
  </main>
</body>
</html>
