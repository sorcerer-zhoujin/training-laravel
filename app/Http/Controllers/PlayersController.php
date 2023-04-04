<?php

namespace App\Http\Controllers;

use App\Http\Resources\PlayerResource;
use App\Models\Player;
use Illuminate\Http\Client\ResponseSequence;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;

class PlayersController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        return new Response(
            Player::query()->
            select(['id', 'name', 'hp', 'mp', 'money'])->
            get());
    }

    /**
     * Display the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function show($id)
    {
        return new Response(
            Player::find($id)
        );
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request)
    {
        //$input = $request->all();
        $input = [
            'name' => $request->input('name'),
            'hp' => $request->input('hp'),
            'mp' => $request->input('mp'),
            'money' => $request->input('money')
        ];

        return new Response(['id' => Player::insertGetId($input)]);
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, $id)
    {
        $input = [
            'name' => $request->input('name', Player::query()->where('id', $id)->value('name')),
            'hp' => $request->input('hp', Player::query()->where('id', $id)->value('hp')),
            'mp' => $request->input('mp', Player::query()->where('id', $id)->value('mp')),
            'money' => $request->input('money', Player::query()->where('id', $id)->value('money'))
        ];

        Player::query()->where('id', $id)->update($input);

        return null;
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function destroy($id)
    {
        Player::find($id)->delete();
        return null;
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function create()
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function edit($id)
    {
        //
    }
}
