<!doctype html>
<html lang="ar" dir="rtl">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <meta name="referrer" content="no-referrer">
  <title>انتهت الجلسة — Mart.ps</title>
  <link rel="stylesheet" href="{{ asset('assets/error.css') }}">
</head>
<body class="error-page error-page--warning">
  <main class="card">
    <p class="code" aria-hidden="true">419</p>
    <h1>انتهت الجلسة</h1>
    <p class="message">حدّث الصفحة وحاول مجددًا. لم يتم تنفيذ الطلب القديم.</p>
    <div class="actions">
      <a class="primary" href="{{ route('login') }}">تسجيل الدخول</a>
      <a href="{{ route('home') }}">الصفحة الرئيسية</a>
    </div>
  </main>
</body>
</html>
