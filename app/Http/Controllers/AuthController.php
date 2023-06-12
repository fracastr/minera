<?php

namespace App\Http\Controllers;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\Models\User;
use stdClass;
use Validator;

class AuthController extends Controller
{
    /**
    * Create user
    *
    * @param  [string] name
    * @param  [string] email
    * @param  [string] password
    * @param  [string] password_confirmation
    * @return [string] message
    */
    public function register(Request $request)
    {
        $request->validate([
            'name' => 'required|string',
            'email'=>'required|string|unique:users',
            'password'=>'required|string',
            'c_password' => 'required|same:password'
        ]);

        $user = new User([
            'nombre'  => $request->name,
            'email' => $request->email,
            'password' => bcrypt($request->password),
        ]);

        if($user->save()){
            $tokenResult = $user->createToken('Personal Access Token');
            $token = $tokenResult->plainTextToken;

            return response()->json([
            'message' => 'Successfully created user!',
            'accessToken'=> $token,
            ],201);
        }
        else{
            return response()->json(['error'=>'Ocurrio un error al registrar un usuario']);
        }
    }

    /**
     * Login user and create token
    *
    * @param  [string] email
    * @param  [string] password
    * @param  [boolean] remember_me
    */

    public function login(Request $request)
    {
        $request->validate([
        'email' => 'required|string|email',
        'password' => 'required|string',
        'remember_me' => 'boolean'
        ]);

        $credentials = request(['email','password']);
        if(!Auth::attempt($credentials))
        {
        return response()->json([
            'message' => 'Unauthorized'
        ],401);
        }

        $user = $request->user();
        $tokenResult = $user->createToken('Personal Access Token');
        $token = $tokenResult->plainTextToken;

        $userData = new stdClass();
        $userData->ability = [
                  (object)[
                    'action' => 'manage',
                    'subject'=> 'all',
                  ],
                ];
        $userData->id = $user->id;
        $userData->fullName = $user->nombre;
        $userData->username = $user->email;
        $userData->email = $user->email;
        $userData->role = 'admin';


        // {
        //     id: 1,
        //     fullName: 'John Doe',
        //     username: 'johndoe',
        //     password: 'admin',
        //     // eslint-disable-next-line global-require
        //     avatar: require('@/assets/images/avatars/13-small.png'),
        //     email: 'admin@demo.com',
        //     role: 'admin',
        //     ability: [
        //       {
        //         action: 'manage',
        //         subject: 'all',
        //       },
        //     ],
        //     extras: {
        //       eCommerceCartItemsCount: 5,
        //     },
        //   }

        return response()->json([
        'accessToken' =>$token,
        'token_type' => 'Bearer',
        'userData' => $userData
        ]);
    }

    /**
     * Get the authenticated User
    *
    * @return [json] user object
    */
    public function user(Request $request)
    {
        return response()->json($request->user());
    }

    /**
     * Logout user (Revoke the token)
    *
    * @return [string] message
    */
    public function logout(Request $request)
    {
        $request->user()->tokens()->delete();

        return response()->json([
        'message' => 'Successfully logged out'
        ]);

    }
}
