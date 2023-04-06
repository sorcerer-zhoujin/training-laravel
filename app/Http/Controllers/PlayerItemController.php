<?php

namespace App\Http\Controllers;

use App\Models\PlayerItem;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class PlayerItemController extends Controller
{
    public function add(Request $request, $id)
    {
        $target = PlayerItem::query()->where(['player_id' => $id, 'item_id' => $request->input('itemId')]);
        $num = $request->input('count');
        // プレーヤーは既にアイテムも持っている場合（加算）
        if ($target->exists()) {
            $num += $target->value('count');
            $target->update(['count' => $num]);
        }
        // プレーヤーは指定されたアイテムを持っていない場合（追加）
        else {
            PlayerItem::insertGetId([
                'player_id' => $id,
                'item_id' => $request->input('itemId'),
                'count' => $num
            ]);
        }

        return new Response([
            'itemId' => $request->input('itemId'),
            'count' => $num
        ]);
    }
}
