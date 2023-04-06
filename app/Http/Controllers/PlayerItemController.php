<?php

namespace App\Http\Controllers;

use App\Models\PlayerItem;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use App\Models\Player;
use App\Models\Item;
use LDAP\Result;

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

    public function use(Request $request, $id)
    {
        $maxHp = 200;
        $maxMp = 200;

        // データ
        $target = PlayerItem::query()->where(['player_id' => $id, 'item_id' => $request->input('itemId')]);
        // プレーヤー情報
        $player = Player::query()->where('id', $id);
        $playerHp = $player->value('hp');
        $playerMp = $player->value('mp');

        // アイテムのない場合
        if ($target->doesntExist() || $target->value('count') < 1) {
            return new Response('アイテムなし', 400);
        }
        // アイテム情報
        $itemValue = Item::query()->where('id', $target->value('item_id'))->value('value');
        $itemCount = $target->value('count');
        if ($itemCount < $request->input('count'))
        {
            return new Response('アイテム不足', 400);
        }

        // HP/MPは上限になった場合
        if ($playerHp >= $maxHp || $playerMp >= $maxMp)
        {
            return new Response('HP/MPは上限になったため、アイテム使用不可', 400);
        }

        // HP回復
        if ($target->value('item_id') == 1) {
            for ($i = $request->input('count'); $i > 0; $i--) {
                $playerHp = ($playerHp + $itemValue) < $maxHp ? ($playerHp + $itemValue) : $maxHp;
                $itemCount--;
                if ($playerHp >= $maxHp) break;
            }
        }
        // MP回復
        if ($target->value('item_id') == 2) {
            for ($i = $request->input('count'); $i > 0; $i--) {
                $playerMp = ($playerMp + $itemValue) < $maxMp ? ($playerMp + $itemValue) : $maxMp;
                $itemCount--;
                if ($playerMp >= $maxMp) break;
            }
            
        }

        // データ更新処理
        $target->update(["count" => $itemCount]);
        $player->update(['hp' => $playerHp, 'mp' => $playerMp]);

        // レスポンス
        return new Response([
            'itemId' => $target->value("item_id"),
            'count' => $itemCount,
            'player' => [
                'id' => $player->value('id'),
                'hp' => $playerHp,
                'mp' => $playerMp
            ]
        ]);
    }
}
