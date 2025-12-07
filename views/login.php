<!DOCTYPE html>
<html lang="pt-BR">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://unpkg.com/alpinejs" defer></script>
    <title>Infovisa - Login</title>
    <style>
        body {
            font-family: 'Poppins', sans-serif;
            background-image: url("data:image/svg+xml,%3Csvg width='60' height='60' viewBox='0 0 60 60' xmlns='http://www.w3.org/2000/svg'%3E%3Cg fill='none' fill-rule='evenodd'%3E%3Cg fill='%233b82f6' fill-opacity='0.05'%3E%3Cpath d='M36 34v-4h-2v4h-4v2h4v4h2v-4h4v-2h-4zm0-30V0h-2v4h-4v2h4v4h2V6h4V4h-4zM6 34v-4H4v4H0v2h4v4h2v-4h4v-2H6zM6 4V0H4v4H0v2h4v4h2V6h4V4H6z'/%3E%3C/g%3E%3C/g%3E%3C/svg%3E");
        }
        
        .glass-effect {
            background: rgba(255, 255, 255, 0.85);
            backdrop-filter: blur(12px);
            -webkit-backdrop-filter: blur(12px);
        }
        
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(-10px); }
            to { opacity: 1; transform: translateY(0); }
        }
        
        .animate-fadeIn {
            animation: fadeIn 0.6s ease-out forwards;
        }
        
        .input-focus-effect:focus {
            border-color: #3b82f6;
            box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.3);
        }
    </style>
</head>

<body class="bg-gradient-to-br from-blue-50 to-indigo-50 flex justify-center items-center min-h-screen p-4">
    <div class="relative w-full max-w-md">
        <!-- Decorative elements -->
        <div class="absolute -top-10 -left-10 w-32 h-32 bg-blue-50 rounded-full opacity-60 z-0"></div>
        <div class="absolute -bottom-10 -right-10 w-32 h-32 bg-indigo-50 rounded-full opacity-60 z-0"></div>
        
        <!-- Main card -->
        <div class="glass-effect rounded-2xl shadow-xl p-8 w-full relative z-10 border border-blue-100 animate-fadeIn">
            <div class="logo mb-8 text-center">
                <img src="/visamunicipal/assets/img/logo.png" alt="Logo Infovisa" class="max-w-40 mx-auto transform transition-transform duration-300 hover:scale-105">
            </div>

            <div class="login-header mb-6 text-center">
                <h2 class="text-xl font-semibold text-gray-800 mb-2">Bem-vindo ao Sistema</h2>
                <p class="text-gray-600 text-sm">Ainda não tem uma conta? <a href="Company/register.php" class="text-blue-500 font-semibold hover:text-blue-700 transition-colors duration-300">Faça seu cadastro</a></p>
            </div>

        <?php
        session_start();
        if (isset($_SESSION['success_message'])) {
            echo '<div class="animate-fadeIn bg-green-50 border border-green-200 text-green-700 p-4 rounded-lg mb-6 flex items-center shadow-sm" role="alert">
                    <div class="flex-shrink-0 bg-green-100 rounded-full p-2 mr-3">
                        <svg class="h-5 w-5 text-green-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                        </svg>
                    </div>
                    <div>
                        <span class="font-medium">Sucesso!</span>
                        <span class="block sm:inline ml-1">' . $_SESSION['success_message'] . '</span>
                    </div>
                  </div>';
            unset($_SESSION['success_message']);
        }
        if (isset($_SESSION['error_message'])) {
            echo '<div class="animate-fadeIn bg-red-50 border border-red-200 text-red-700 p-4 rounded-lg mb-6 flex items-center shadow-sm" role="alert">
                    <div class="flex-shrink-0 bg-red-100 rounded-full p-2 mr-3">
                        <svg class="h-5 w-5 text-red-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                        </svg>
                    </div>
                    <div>
                        <span class="font-medium">Erro!</span>
                        <span class="block sm:inline ml-1">' . $_SESSION['error_message'] . '</span>
                    </div>
                  </div>';
            unset($_SESSION['error_message']);
        }
        ?>

        <form action="../controllers/UserController.php?action=login" method="POST" class="space-y-6" x-data="{ showPassword: false }">
            <!-- Email field -->
            <div class="group relative transition-all duration-300 ease-in-out">
                <label for="email" class="block text-gray-700 text-sm font-medium mb-2">E-mail</label>
                <div class="relative">
                    <div class="absolute inset-y-0 left-0 flex items-center pl-3 pointer-events-none">
                        <svg class="h-5 w-5 text-blue-400" fill="none" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" viewBox="0 0 24 24" stroke="currentColor">
                            <path d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"></path>
                        </svg>
                    </div>
                    <input type="email" id="email" name="email" required 
                           class="input-focus-effect w-full py-3 px-10 bg-white/70 border border-blue-100 rounded-lg text-gray-700 leading-tight focus:outline-none transition-all duration-300 ease-in-out" 
                           placeholder="Digite seu e-mail">
                </div>
            </div>
            
            <!-- Password field -->
            <div class="group relative transition-all duration-300 ease-in-out">
                <label for="senha" class="block text-gray-700 text-sm font-medium mb-2">Senha</label>
                <div class="relative">
                    <div class="absolute inset-y-0 left-0 flex items-center pl-3 pointer-events-none">
                        <svg class="h-5 w-5 text-blue-400" fill="none" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" viewBox="0 0 24 24" stroke="currentColor">
                            <path d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2z"></path>
                            <path d="M10 13a2 2 0 104 0 2 2 0 00-4 0z"></path>
                        </svg>
                    </div>
                    <input :type="showPassword ? 'text' : 'password'" id="senha" name="senha" required 
                           class="input-focus-effect w-full py-3 px-10 bg-white/70 border border-blue-100 rounded-lg text-gray-700 leading-tight focus:outline-none transition-all duration-300 ease-in-out" 
                           placeholder="Digite sua senha">
                    <div class="absolute inset-y-0 right-0 flex items-center pr-3 cursor-pointer" @click="showPassword = !showPassword">
                        <svg x-show="!showPassword" class="h-5 w-5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path>
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"></path>
                        </svg>
                        <svg x-show="showPassword" class="h-5 w-5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24" style="display: none;">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.543-7a9.97 9.97 0 011.563-3.029m5.858.908a3 3 0 114.243 4.243M9.878 9.878l4.242 4.242M9.88 9.88l-3.29-3.29m7.532 7.532l3.29 3.29M3 3l18 18"></path>
                        </svg>
                    </div>
                </div>
            </div>
            
            <!-- Submit button -->
            <div class="pt-2">
                <button type="submit" 
                        class="w-full py-3 px-6 bg-gradient-to-r from-blue-500 to-indigo-600 hover:from-blue-600 hover:to-indigo-700 text-white font-medium rounded-lg shadow-md hover:shadow-lg transition-all duration-300 ease-in-out transform hover:-translate-y-1 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-opacity-50">
                    <div class="flex items-center justify-center">
                        <svg class="h-5 w-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 16l-4-4m0 0l4-4m-4 4h14m-5 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h7a3 3 0 013 3v1"></path>
                        </svg>
                        <span>Entrar no Sistema</span>
                    </div>
                </button>
            </div>
            
            <!-- Remember me and forgot password -->
            <div class="flex items-center justify-between mt-6 text-sm">
                <div class="flex items-center">
                    <input type="checkbox" id="remember" name="remember" class="h-4 w-4 text-blue-500 focus:ring-blue-400 border-gray-300 rounded">
                    <label for="remember" class="ml-2 block text-gray-600">Lembrar-me</label>
                </div>
                <a href="recuperar_senha.php" class="text-blue-500 hover:text-blue-700 font-medium transition-colors duration-300">Esqueceu a senha?</a>
            </div>
        </form>

        <div class="mt-8 pt-6 border-t border-gray-200 text-center">
            <p class="text-gray-600 text-xs">&copy; 2025 Infovisa. Todos os direitos reservados.</p>
        </div>
    </div>
</div>
</body>

</html>