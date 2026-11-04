<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Engineering Lab — Admin Login</title>
</head>
<body>
<h1>Engineering Lab</h1>
<h2>Administrator Login</h2>

@if ($errors->any())
    <p style="color: red;">
        {{ $errors->first() }}
    </p>
@endif

<form method="POST" action="{{ route('login.submit') }}">
    @csrf

    <div>
        <label for="email">Email</label>
        <input
            type="email"
            id="email"
            name="email"
            value="{{ old('email') }}"
            required
            autofocus
        >
    </div>

    <div>
        <label for="password">Password</label>
        <input
            type="password"
            id="password"
            name="password"
            required
        >
    </div>

    <button type="submit">Sign in</button>
</form>
</body>
</html>
