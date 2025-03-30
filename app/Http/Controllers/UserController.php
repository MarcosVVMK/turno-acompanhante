<?php

    namespace App\Http\Controllers;

    use App\Models\User;
    use Illuminate\Http\Request;

    class UserController extends Controller
    {
        /**
         * Retorna uma listagem de todos os usuários.
         *
         * @return \Illuminate\Http\JsonResponse
         */
        public function index()
        {
            // Selecionamos apenas id e name por razões de segurança e performance
            $users = User::select('id', 'name')->get();

            return response()->json($users);
        }
    }
