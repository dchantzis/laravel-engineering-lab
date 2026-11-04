<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Authorize Application</title>
</head>
<body>
<h1>Authorize Application</h1>

<p>
    Application: {{ $client->name }}
</p>

<p>
    Do you want to authorize this application?
</p>

<form method="POST" action="{{ route('passport.authorizations.approve') }}">
    @csrf

    <input type="hidden" name="state" value="{{ $request->state }}">
    <input type="hidden" name="client_id" value="{{ $client->getKey() }}">
    <input type="hidden" name="auth_token" value="{{ $authToken }}">

    <button type="submit">Authorize</button>
</form>

<form method="POST" action="{{ route('passport.authorizations.deny') }}">
    @csrf
    @method('DELETE')

    <input type="hidden" name="state" value="{{ $request->state }}">
    <input type="hidden" name="client_id" value="{{ $client->getKey() }}">
    <input type="hidden" name="auth_token" value="{{ $authToken }}">

    <button type="submit">Cancel</button>
</form>
</body>
</html>
