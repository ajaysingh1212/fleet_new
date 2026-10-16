<x-guest-layout>
    <form method="POST" action="{{ route('register') }}">
        @csrf
        <p class="login-box-msg">Register a new account</p>
        <div class="input-group mb-3">
            <input id="name" type="text" class="form-control @error('name') is-invalid @enderror" name="name" value="{{ old('name') }}" required autofocus autocomplete="name" placeholder="Full name">
            <div class="input-group-text"><span class="fas fa-user"></span></div>
            @error('name')<div class="invalid-feedback">{{ $message }}</div>@enderror
        </div>
        <div class="input-group mb-3">
            <input id="email" type="email" class="form-control @error('email') is-invalid @enderror" name="email" value="{{ old('email') }}" required autocomplete="username" placeholder="Email">
            <div class="input-group-text"><span class="fas fa-envelope"></span></div>
            @error('email')<div class="invalid-feedback">{{ $message }}</div>@enderror
        </div>
        <div class="input-group mb-3">
            <input id="phone" type="text" class="form-control @error('phone') is-invalid @enderror" name="phone" value="{{ old('phone') }}" placeholder="Phone">
            <div class="input-group-text"><span class="fas fa-phone"></span></div>
            @error('phone')<div class="invalid-feedback">{{ $message }}</div>@enderror
        </div>
        <div class="input-group mb-3">
            <input id="password" type="password" class="form-control @error('password') is-invalid @enderror" name="password" required autocomplete="new-password" placeholder="Password">
            <div class="input-group-text"><span class="fas fa-lock"></span></div>
            @error('password')<div class="invalid-feedback">{{ $message }}</div>@enderror
        </div>
        <div class="input-group mb-3">
            <input id="password_confirmation" type="password" class="form-control" name="password_confirmation" required autocomplete="new-password" placeholder="Confirm password">
            <div class="input-group-text"><span class="fas fa-lock"></span></div>
        </div>
        <div class="row">
            <div class="col-8">
                <a href="{{ route('login') }}" class="text-center">I already have an account</a>
            </div>
            <div class="col-4">
                <button type="submit" class="btn btn-primary w-100">Register</button>
            </div>
        </div>
    </form>
</x-guest-layout>
