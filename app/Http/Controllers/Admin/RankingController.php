<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Competition;
use App\Models\LeaderboardEntry;
use Illuminate\Http\Request;
use Illuminate\View\View;

class RankingController extends Controller
{
    /** admin.ranking.index — 11.2: không trộn phạm vi, không lộ rank tạm khi "Chờ công bố". */
    public function index(Request $request): View
    {
        $boards = Competition::withCount('leaderboardEntries')->latest('starts_at')->limit(20)->get()->map(fn ($c) => [
            'id' => $c->id,
            'scope' => 'Cuộc thi: '.$c->title,
            'entries' => $c->ranksArePublic() ? $c->leaderboard_entries_count : 0,
            'status' => $c->ranksArePublic() ? 'Đã công bố' : 'Chờ công bố',
            'tone' => $c->ranksArePublic() ? 'success' : 'neutral',
        ])->all();

        // Bổ sung các bảng xếp hạng theo lớp (scope=class_room) đang có dữ liệu.
        $classScopes = LeaderboardEntry::where('scope', 'class_room')->select('class_room_id')->distinct()->pluck('class_room_id');
        foreach ($classScopes as $classRoomId) {
            $classRoom = \App\Models\ClassRoom::find($classRoomId);
            if (! $classRoom) {
                continue;
            }
            $boards[] = [
                'id' => 'class-'.$classRoomId,
                'scope' => 'Lớp: '.$classRoom->name,
                'entries' => LeaderboardEntry::where('scope', 'class_room')->where('class_room_id', $classRoomId)->count(),
                'status' => 'Đã công bố',
                'tone' => 'success',
            ];
        }

        return view('admin.ranking.index', ['boards' => $boards]);
    }
}
