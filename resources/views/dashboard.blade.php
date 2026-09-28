<!DOCTYPE html>
<html lang="en"><head><meta charset="utf-8"><title>Agapay</title></head>
<body>
    <p>{{ auth()->user()->username }}</p>
    <form method="POST" action="{{ route('logout') }}">@csrf<button>Log out</button></form>
</body></html>
