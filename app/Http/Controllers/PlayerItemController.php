<?php

namespace App\Http\Controllers;

use App\Models\PlayerItem;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use App\Models\Player;
use App\Models\Item;
use Exception;
use Illuminate\Support\Facades\DB;

class PlayerItemController extends Controller
{
    const ERR_CODE = 400;

    public function add(Request $request, $id)
    {
        DB::beginTransaction();

        try {
            Player::find($id)->lockForUpdate();
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

            DB::commit();

            return new Response([
            'itemId' => $request->input('itemId'),
            'count' => $num
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['error' => $e->getMessage()], self::ERR_CODE);
        }

    }

    public function use(Request $request, $id)
    {
        // HP/MP上限
        $MAX_HP = 200;
        $MAX_MP = 200;

        DB::beginTransaction();

        try {
            // データクエリ
            $player = Player::query()->where('id', $id)->lockForUpdate();
            $target = PlayerItem::query()->where(['player_id' => $id, 'item_id' => $request->input('itemId')]);
            // プレーヤー情報
            $playerHp = $player->value('hp');
            $playerMp = $player->value('mp');

            // データにアイテムのない（もしくはデータのない）場合
            if ($target->doesntExist() || $target->value('count') < 1) {
                //throw new Exception('アイテムなし');
            }
            // アイテム情報
            $itemValue = Item::query()->where('id', $target->value('item_id'))->value('value');
            $itemCount = $target->value('count');
            if ($itemCount < $request->input('count'))
            {
                //throw new Exception('アイテム不足');
            }

            // HP/MPは上限になった場合
            if ($playerHp >= $MAX_HP || $playerMp >= $MAX_MP)
            {
                throw new Exception('HP/MPは上限になったため、アイテム使用不可');
            }

            // HP回復
            if ($target->value('item_id') == 1) {
                for ($i = $request->input('count'); $i > 0; $i--) {
                    $playerHp = ($playerHp + $itemValue) < $MAX_HP ? ($playerHp + $itemValue) : $MAX_HP;
                    $itemCount--;
                    if ($playerHp >= $MAX_HP) break;
                }
            }
            // MP回復
            if ($target->value('item_id') == 2) {
                for ($i = $request->input('count'); $i > 0; $i--) {
                    $playerMp = ($playerMp + $itemValue) < $MAX_MP ? ($playerMp + $itemValue) : $MAX_MP;
                    $itemCount--;
                    if ($playerMp >= $MAX_MP) break;
                }

            }

            // データ更新処理
            $target->update(["count" => $itemCount]);
            $player->update(['hp' => $playerHp, 'mp' => $playerMp]);

            // コミット
            DB::commit();

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
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['error' => $e->getMessage()], self::ERR_CODE);
        }

    }

    public function gacha(Request $request, $id)
    {
        $COUNT = $request->input('count');     // ガチャ回数
        $COST = 10;                            // ガチャ一回の費用
        $gachaCost = $COST * $COUNT;           // ガチャ合計費用

        DB::beginTransaction();

        try {
            // プレーヤー情報
            $player = Player::query()->where('id', $id)->lockForUpdate();
            // データ存在チェック
            if($player->doesntExist()) return new Exception('プレイヤー情報を見つかりませんでした');
            // プレーヤー所持金
            $playerMoney = $player->value('money');
            // 所持金の判断
            if ($playerMoney < $gachaCost) {
                throw new Exception("ガチャ費用不足");
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
            if (empty($result)) return new Exception('Unknown Error');
            // アイテム個数加算用の変数
            $resultItemCounter = array_fill(0, $itemPool->count() + 1, 0);
            // JSON出力用の配列
            $resultJson = [];

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

            // 結果によるデータを更新(メモリ上)
            foreach ($result as $itemId) {
                $resultItemCounter[$itemId]++;
                // ハズレなら更新不要
                if ($itemId == 0) continue;
                //　プレーヤーのアイテム情報は存在しない場合は新規作成
                if (!isset($playerItems[$itemId])) {
                    $playerItems[$itemId] = ['count' => 1];
                }
            }
            // データベース更新処理（$itemPoolのアイテム数回のループ）
            foreach ($itemPool as $item){
                // アイテム加算
                $playerItems[$item->id]['count'] += $resultItemCounter[$item->id];
                // JSON出力を作る
                $resultJson[] =[
                    'itemId' => $item->id,
                    'count' => $resultItemCounter[$item->id]
                ];
                // データベースにアクセス、更新
                PlayerItem::query()
                ->where('player_id', $id)
                ->where('item_id', $item->id)
                ->update(['count' => $playerItems[$item->id]['count']]);
            }

            DB::commit();

            // レスポンス
            return new Request([
                'results' => $resultJson,
                'player' => [
                    'money' => $playerMoney,
                    'items' => $playerItems
                ]
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['error' => $e->getMessage()], self::ERR_CODE);
        }


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
