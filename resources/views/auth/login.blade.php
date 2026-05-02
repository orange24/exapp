<!DOCTYPE html>
<html lang="th" class="h-full">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>เข้าสู่ระบบ — Exchange System</title>
    <link rel="icon" type="image/png" href="{{ asset('favicon.png') }}">
    <link rel="apple-touch-icon" href="{{ asset('apple-touch-icon.png') }}">
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        body { background: linear-gradient(135deg, #1a2a3a 0%, #2d3e50 50%, #1a2a3a 100%); }
    </style>
</head>
<body class="h-full flex items-center justify-center p-4" x-data="{ showPass: false }">

    <div style="background:#f5f5f5; border-radius:20px; width:100%; max-width:480px; padding:50px 40px; box-shadow: 0 25px 80px rgba(0,0,0,0.3);">

        {{-- Logo --}}
        <div style="text-align:center; margin-bottom:36px;">
            <img src="{{ asset('images/logo.png') }}" alt="Exchange System"
                 style="max-height:60px; margin:0 auto;">
        </div>

        {{-- Error --}}
        @if (session('error'))
            <div style="margin-bottom:16px; padding:10px 14px; background:#fef2f2; border:1px solid #fca5a5; color:#dc2626; border-radius:8px; font-size:13px;">
                {{ session('error') }}
            </div>
        @endif

        <form method="POST" action="{{ route('login.post') }}">
            @csrf

            {{-- Email --}}
            <div style="margin-bottom:20px;">
                <label style="display:block; font-size:13px; font-weight:600; color:#333; margin-bottom:6px;">
                    Email Address
                </label>
                <input type="email" name="email" value="{{ old('email') }}"
                       placeholder="user@example.com" required autofocus
                       style="width:100%; padding:12px 14px; border:1px solid #ddd; border-radius:8px; font-size:14px; background:#fff; outline:none; transition:border 0.2s;"
                       onfocus="this.style.borderColor='#137050'" onblur="this.style.borderColor='#ddd'">
                @error('email')
                    <p style="margin-top:4px; font-size:12px; color:#dc2626;">{{ $message }}</p>
                @enderror
            </div>

            {{-- Password --}}
            <div style="margin-bottom:20px;">
                <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:6px;">
                    <label style="font-size:13px; font-weight:600; color:#333;">Password</label>
                </div>
                <div style="position:relative;">
                    <input :type="showPass ? 'text' : 'password'" name="password" required
                           placeholder="••••••••••"
                           style="width:100%; padding:12px 42px 12px 14px; border:1px solid #ddd; border-radius:8px; font-size:14px; background:#fff; outline:none; transition:border 0.2s;"
                           onfocus="this.style.borderColor='#137050'" onblur="this.style.borderColor='#ddd'">
                    <button type="button" @click="showPass = !showPass"
                            style="position:absolute; right:12px; top:50%; transform:translateY(-50%); background:none; border:none; cursor:pointer; color:#999; padding:0;">
                        <svg x-show="!showPass" style="width:20px; height:20px;" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/>
                        </svg>
                        <svg x-show="showPass" x-cloak style="width:20px; height:20px;" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.543-7a9.97 9.97 0 011.563-3.029m5.858.908a3 3 0 114.243 4.243M9.878 9.878l4.242 4.242M9.878 9.878L3 3m6.878 6.878L21 21"/>
                        </svg>
                    </button>
                </div>
                @error('password')
                    <p style="margin-top:4px; font-size:12px; color:#dc2626;">{{ $message }}</p>
                @enderror
            </div>

            {{-- Remember --}}
            <div style="margin-bottom:24px;">
                <label style="display:flex; align-items:center; gap:8px; font-size:13px; color:#555; cursor:pointer;">
                    <input type="checkbox" name="remember"
                           style="width:18px; height:18px; accent-color:#137050; border-radius:4px; cursor:pointer;">
                    Keep me signed in
                </label>
            </div>

            {{-- Login button --}}
            <button type="submit"
                    style="width:100%; padding:14px; background:#137050; color:#fff; border:none; border-radius:8px; font-size:15px; font-weight:600; cursor:pointer; transition:background 0.2s; letter-spacing:0.5px;"
                    onmouseover="this.style.background='#0e5a3f'" onmouseout="this.style.background='#137050'">
                Login
            </button>
        </form>

        <p style="text-align:center; margin-top:24px; font-size:11px; color:#aaa;">
            Powered by <a href="https://softernity.com" target="_blank" style="color:#137050; text-decoration:none; font-weight:600;">Softernity</a>
        </p>
    </div>

    <script src="//unpkg.com/alpinejs@3.x.x/dist/cdn.min.js" defer></script>
</body>
</html>
