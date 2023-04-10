<?php

namespace App\Http\Controllers;

use App\Models\PlayerItem;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use App\Models\Player;
use App\Models\Item;

class PlayerItemController extends Controller
{
    public function add(Request $request, $id)
    {
        $target = PlayerItem::query()->where(['player_id' => $id, 'itemId' => $request->input('itemId')]);
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
                'itemId' => $request->input('itemId'),
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
        // エラーコード
        $ERR_CODE = 400;

        // HP/MP上限
        $MAX_HP = 200;
        $MAX_MP = 200;

        // データ
        $target = PlayerItem::query()->where(['player_id' => $id, 'itemId' => $request->input('itemId')]);
        // プレーヤー情報
        $player = Player::query()->where('id', $id);
        $playerHp = $player->value('hp');
        $playerMp = $player->value('mp');

        // データにアイテムのない（もしくはデータのない）場合
        if ($target->doesntExist() || $target->value('count') < 1) {
            return new Response('アイテムなし', $ERR_CODE);
        }
        // アイテム情報
        $itemValue = Item::query()->where('id', $target->value('itemId'))->value('value');
        $itemCount = $target->value('count');
        if ($itemCount < $request->input('count'))
        {
            return new Response('アイテム不足', $ERR_CODE);
        }

        // HP/MPは上限になった場合
        if ($playerHp >= $MAX_HP || $playerMp >= $MAX_MP)
        {
            return new Response('HP/MPは上限になったため、アイテム使用不可', $ERR_CODE);
        }

        // HP回復
        if ($target->value('itemId') == 1) {
            for ($i = $request->input('count'); $i > 0; $i--) {
                $playerHp = ($playerHp + $itemValue) < $MAX_HP ? ($playerHp + $itemValue) : $MAX_HP;
                $itemCount--;
                if ($playerHp >= $MAX_HP) break;
            }
        }
        // MP回復
        if ($target->value('itemId') == 2) {
            for ($i = $request->input('count'); $i > 0; $i--) {
                $playerMp = ($playerMp + $itemValue) < $MAX_MP ? ($playerMp + $itemValue) : $MAX_MP;
                $itemCount--;
                if ($playerMp >= $MAX_MP) break;
            }
            
        }

        // データ更新処理
        $target->update(["count" => $itemCount]);
        $player->update(['hp' => $playerHp, 'mp' => $playerMp]);

        // レスポンス
        return new Response([
            'itemId' => $target->value("itemId"),
            'count' => $itemCount,
            'player' => [
                'id' => $player->value('id'),
                'hp' => $playerHp,
                'mp' => $playerMp
            ]
        ]);
    }

    public function gacha(Request $request, $id)
    {
        $COUNT = $request->input('count');     // ガチャ回数
        $COST = 10;                            // ガチャ一回の費用
        $gachaCost = $COST * $COUNT;           // ガチャ合計費用

        // プレーヤー情報
        $player = Player::query()->where('id', $id);
        $playerMoney = $player->value('money');
        // 所持金の判断
        if ($playerMoney < $gachaCost) {
            return new Response('ガチャ費用不足');
        }
        // お金かかる
        else {
            $playerMoney -= $gachaCost;
            $player->update([
                'money' => $playerMoney
            ]);
        }

        // すべてのアイテムのデータ
        $itemPool = Item::get();
        // アイテムデータから確率を取得して配列に保存
        $lootPercent = [];
        foreach ($itemPool as $item) {
            $lootPercent[$item->id] = $item->percent;
        }

        // ガチャ結果用の配列
        $result = $this->lottery($lootPercent, $COUNT);
        // アウトプット用の配列
        $resultItemCounter = array_fill(0, $itemPool->count() + 1, 0);
        if (empty($result)) return;

        // プレーヤーのアイテム情報を配列に保存
        $playerItems = PlayerItem::where('player_id', $id)
            ->get()
            ->keyBy('item_id')
            ->map(function ($item) {
                return [
                    'itemId' => $item->item_id,
                    'count' => $item->count
                ];
            })
            ->toArray();
        
        // 結果によるデータを更新
        foreach ($result as $itemId) {
            $resultItemCounter[$itemId]++;
            // ハズレ
            if ($itemId == 0) continue;
            //　プレーヤーのアイテム情報は既にテーブルに存在する場合、更新のみ
            if (isset($playerItems[$itemId])) {
                $playerItems[$itemId]['count']++;
                PlayerItem::query()
                    ->where('player_id', $id)
                    ->where('item_id', $itemId)
                    ->update(['count' => $playerItems[$itemId]['count']]);
            }
            //　プレーヤーのアイテム情報は存在しない、新規作成
            else {
                PlayerItem::query()
                    ->insert([
                    'player_id' => $id,
                    'item_id' => $itemId,
                    'count' => 1
                ]);
                $playerItems[$itemId] = ['count' => 1];
            }
        }
        
        // レスポンス
        return new Request([
            'results' => [
                'itemId' => array_keys($resultItemCounter),
                'count' => array_values($resultItemCounter)
            ],
            'player' => [
                'money' => $playerMoney,
                'items' => $playerItems
            ]
        ]); 
    }

    // ガチャ結果計算用の関数
    private function lottery($lootPercent, $numTimes) {
        $MAX_PERCENT = 100;
        $totalPercent = array_sum($lootPercent);
    
        if ($totalPercent < $MAX_PERCENT) {
            $lootPercent[0] = $MAX_PERCENT - $totalPercent;
            $totalPercent = $MAX_PERCENT;
        }

        $result = array();

        for ($i = 0; $i < $numTimes; $i++) {
            $random = rand(1, $totalPercent);
            $currentPercent = 0;
    
            foreach ($lootPercent as $id => $percent) {
                $currentPercent += $percent;
    
                if ($random <= $currentPercent) {
                    $result[] = $id;
                    break;
                }
            }
        }
    
        return $result;
    }
}
