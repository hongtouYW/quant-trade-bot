<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Question;
use App\Models\Questionrelated;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class QuestionController extends Controller
{
    public function __construct()
    {
    }

    public function index(Request $request)
    {
        $authorizedUser = Auth::user();
        if (!$authorizedUser) {
            return redirect()->route('dashboard')->with('error', __('messages.Unauthorized'));
        }
        if (!CheckAuthorizedView($authorizedUser->user_id, GetModuleID('question_management'))) {
            return response(__('messages.Unauthorized'), 403);
        }
        try {
            $query = Question::query();
            if ($authorizedUser->user_role !== 'masteradmin') {
                $query->where('agent_id', $authorizedUser->agent_id);
            }
            if ($request->filled('search')) {
                $searchTerm = $request->input('search');
                $query->where(function ($q) use ($searchTerm) {
                    $q->where('title', 'LIKE', '%' . $searchTerm . '%')
                      ->orWhere('question_type', 'LIKE', '%' . $searchTerm . '%')
                      ->orWhere('question_desc', 'LIKE', '%' . $searchTerm . '%');
                });
            }
            if ($request->filled('status')) {
                $query->where('status', $request->input('status'));
            }
            if ($request->filled('delete')) {
                $query->where('delete', $request->input('delete'));
            }
            $query = queryBetweenDateEloquent($query, $request, 'created_on');
            $questions = $query->orderBy('created_on', 'desc')->paginate(10)->appends($request->all());
            return view('module.question.list', ['questions' => $questions]);
        } catch (\Exception $e) {
            Log::error("Error fetching question list: " . $e->getMessage());
            return redirect()->back()->with('error', __('messages.api_error'));
        }
    }

    /**
     * Show the form for creating a new question.
     * @return \Illuminate\View\View
     */
    public function create()
    {
        $authorizedUser = Auth::user();
        if (!$authorizedUser) {
            return redirect()->route('dashboard')->with('error', __('messages.Unauthorized'));
        }
        if (!CheckAuthorizedEdit($authorizedUser->user_id, GetModuleID('question_management'))) {
            return response(__('messages.Unauthorized'), 403);
        }
        $types = ['commonquestion','vip'];
        $langs = config('languages.supported');
        return view('module.question.create', compact('types','langs'));
    }

    /**
     * Store a newly created user in storage.
     * Corresponds to your API's 'add' method logic.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\RedirectResponse
     */
    public function store(Request $request)
    {
        $authorizedUser = Auth::user();
        if (!$authorizedUser) {
            return redirect()->route('dashboard')->with('error', __('messages.Unauthorized'));
        }
        if (!CheckAuthorizedEdit($authorizedUser->user_id, GetModuleID('question_management'))) {
            return response(__('messages.Unauthorized'), 403);
        }

        $validator = Validator::make($request->all(), [
            'title' => 'required|string|max:50',
            'question_type' => 'required|string|max:50|in:commonquestion,transactioncontrol,transfer,vip',
            'question_desc' => 'nullable|string|max:10000',
            'picture' => 'nullable|image|mimes:jpeg,png,jpg,gif|max:2048',
            'language' => 'required|string',

            // 🔽 related questions
            'related' => 'nullable|array',
            'related.*.title' => 'nullable|string|max:200',
            'related.*.question_desc' => 'nullable|string|max:10000',
            'related.*.picture' => 'nullable|image|mimes:jpeg,png,jpg,gif|max:2048',
        ]);

        if ($validator->fails()) {
            return redirect()->back()->withErrors($validator)->withInput();
        }

        try {
            $picture = null;
            if ($request->hasFile('picture') && $request->file('picture')->isValid()) {
                $sanitizedName = Str::slug($validator->validated()['title'], '_');
                $extension = $request->file('picture')->getClientOriginalExtension();
                $filename = $sanitizedName.'_' . time() . '.' . $extension;
                $picture = $request->file('picture')->storeAs(
                    'assets/img/question',
                    $filename,
                    'public'
                );
            }
            $question_id = DB::table('tbl_question')->insertGetId([
                'question_type' => $request->input('question_type'),
                'title' => $request->input('title'),
                'question_desc' => $request->input('question_desc') ?? null,
                'picture' => $picture,
                'agent_id' => $authorizedUser->agent_id,
                'lang' => $request->input('language'),
                'status' => 1,
                'delete' => 0,
                'created_on' => now(),
                'updated_on' => now(),
            ]);
            if ($request->filled('related')) {
                foreach ($request->related as $related) {

                    // skip empty rows
                    if (empty($related['question_desc']) && empty($related['picture'])) {
                        continue;
                    }

                    $relatedPicturePath = null;

                    if (!empty($related['picture']) && $related['picture']->isValid()) {

                        $sanitizedName = Str::slug($request->input('title'), '_');
                        $extension = $related['picture']->getClientOriginalExtension();

                        // add uniqid / index to prevent overwrite
                        $filename = $sanitizedName . '_related_' . Str::uuid() . '.' . $extension;

                        $relatedPicturePath = $related['picture']->storeAs(
                            'assets/img/question/related',
                            $filename,
                            'public'
                        );
                    }

                    Questionrelated::create([
                        'question_id'   => $question_id,
                        'question_desc' => $related['question_desc'] ?? null,
                        'title'         => $related['title'] ?? null,
                        'picture'       => $relatedPicturePath, // ✅ correct
                        'lang'          => $request->input('language'),
                        'status'        => 1,
                        'delete'        => 0,
                        'created_on'    => now(),
                        'updated_on'    => now(),
                    ]);
                }
            }
            return redirect()->route('admin.question.index')->with('success', __('question.question_added_successfully',['title' => $request->input('title'),]));

        } catch (\Illuminate\Database\QueryException $e) {
            Log::error("Error adding question: " . $e->getMessage());
            return redirect()->back()->with('error', __('messages.api_error'));
        }
    }

    /**
     * Show the form for editing the specified question.
     *
     * @param  int  $id
     * @return \Illuminate\View\View|\Illuminate\Http\RedirectResponse
     */
    public function edit($id)
    {
        $authorizedUser = Auth::user();
        if (!$authorizedUser) {
            return redirect()->route('dashboard')->with('error', __('messages.Unauthorized'));
        }
        if (!CheckAuthorizedEdit($authorizedUser->user_id, GetModuleID('question_management'))) {
            return response(__('messages.Unauthorized'), 403);
        }
        $question = DB::table('tbl_question')->where('question_id', $id)->first();
        if (!$question) {
            return redirect()->route('admin.question.index')->with('error', __('messages.nodata'));
        }
        $relateds = Questionrelated::where('question_id', $id)
                            ->where('status', 1)
                            ->where('delete', 0)
                            ->get();
        $types = ['commonquestion','vip'];
        $langs = config('languages.supported');
        return view('module.question.edit', compact('question','relateds','types','langs'));
    }

    /**
     * Update the specified user in storage.
     * Corresponds to your API's 'edit' method logic.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\RedirectResponse
     */
    public function update(Request $request, $id)
    {
        $authorizedUser = Auth::user();
        if (!$authorizedUser) {
            return redirect()->route('dashboard')->with('error', __('messages.Unauthorized'));
        }
        if (!CheckAuthorizedEdit($authorizedUser->user_id, GetModuleID('question_management'))) {
            return response(__('messages.Unauthorized'), 403);
        }

        $question = DB::table('tbl_question')->where('question_id', $id)->first();
        if (!$question) {
            return redirect()->back()->with('error', __('messages.nodata'));
        }

        $validator = Validator::make($request->all(), [
            'title' => 'required|string|max:50',
            'question_type' => 'required|string|max:50|in:commonquestion,transactioncontrol,transfer,vip',
            'question_desc' => 'nullable|string|max:10000',
            'picture' => 'nullable|image|mimes:jpeg,png,jpg,gif|max:2048',
            'status' => 'nullable|in:1,0',

            // 🔽 related questions
            'related' => 'nullable|array',
            'related.*.questionrelated_id' => 'nullable|integer|exists:tbl_questionrelated,questionrelated_id',
            'related.*.title' => 'nullable|string|max:200',
            'related.*.question_desc' => 'nullable|string|max:10000',
            'related.*.picture' => 'nullable|image|mimes:jpeg,png,jpg,gif|max:2048',
        ]);

        if ($validator->fails()) {
            return redirect()->back()->withErrors($validator)->withInput();
        }

        try {
            $picture = $question->picture;
            if ($request->hasFile('picture') && $request->file('picture')->isValid()) {
                if ($question->picture && Storage::disk('public')->exists($question->picture)) {
                    Storage::disk('public')->delete($question->picture);
                }
                $sanitizedName = Str::slug($validator->validated()['title'], '_');
                $extension = $request->file('picture')->getClientOriginalExtension();
                $filename = $sanitizedName.'_' . time() . '.' . $extension;
                $picture = $request->file('picture')->storeAs(
                    'assets/img/question',
                    $filename,
                    'public'
                );
            }
            $updateData = [
                'question_type' => $request->input('question_type'),
                'title' => $request->input('title'),
                'question_desc' => $request->input('question_desc') ?? null,
                'picture' => $picture,
                'status' => $request->filled('status') ? $request->input('status'): 0,
                'updated_on' => now(),
            ];
            DB::table('tbl_question')->where('question_id', $id)->update($updateData);

            // 2️⃣ CREATE / UPDATE related questions
            foreach ($request->related ?? [] as $related) {

                if (empty($related['question_desc']) && empty($related['picture'])) {
                    continue;
                }

                $relatedPicturePath = null;

                if (!empty($related['picture']) && $related['picture']->isValid()) {
                    $sanitizedName = Str::slug($request->input('title'), '_');
                    $extension = $related['picture']->getClientOriginalExtension();
                    $filename = $sanitizedName.'_related_'.uniqid().'.'.$extension;

                    $relatedPicturePath = $related['picture']->storeAs(
                        'assets/img/question/related',
                        $filename,
                        'public'
                    );
                }

                if (!empty($related['questionrelated_id'])) {
                    $existing = Questionrelated::find($related['questionrelated_id']);

                    if ($relatedPicturePath && $existing->picture) {
                        Storage::disk('public')->delete($existing->picture);
                    }

                    Questionrelated::where('questionrelated_id', $related['questionrelated_id'])
                        ->update([
                            'question_desc' => $related['question_desc'] ?? null,
                            'picture' => $relatedPicturePath ?? $existing->picture,
                            'updated_on' => now(),
                        ]);
                } else {
                    Questionrelated::create([
                        'question_id' => $id,
                        'title' => $related['title'] ?? null,
                        'question_desc' => $related['question_desc'] ?? null,
                        'picture' => $relatedPicturePath,
                        'lang' => $request->input('language'),
                        'status' => 1,
                        'delete' => 0,
                        'created_on' => now(),
                        'updated_on' => now(),
                    ]);
                }
            }

            // 3️⃣ 🔥 NOW put your REMOVE logic HERE 🔥
            $existingIds = Questionrelated::where('question_id', $id)
                ->where('delete', 0)
                ->pluck('questionrelated_id')
                ->toArray();

            $submittedIds = collect($request->related ?? [])
                ->pluck('questionrelated_id')
                ->filter()
                ->toArray();

            $removedIds = array_diff($existingIds, $submittedIds);

            if (!empty($removedIds)) {
                $toDelete = Questionrelated::whereIn('questionrelated_id', $removedIds)->get();

                foreach ($toDelete as $row) {
                    if ($row->picture) {
                        Storage::disk('public')->delete($row->picture);
                    }
                }

                Questionrelated::whereIn('questionrelated_id', $removedIds)
                    ->update([
                        'delete' => 1,
                        'updated_on' => now(),
                    ]);
            }

            return redirect()->route('admin.question.index')->with('success', __('question.question_updated_successfully',['title' => $request->input('title'),]));
        } catch (\Illuminate\Database\QueryException $e) {
            Log::error("Error updating question: " . $e->getMessage());
            return redirect()->back()->with('error', __('messages.api_error'));
        }
    }

    /**
     * Remove the specified user from storage (soft delete).
     * Corresponds to your API's 'delete' method logic.
     *
     * @param  int  $id
     * @return \Illuminate\Http\RedirectResponse
     */
    public function destroy($id)
    {
        $authorizedUser = Auth::user();
        if (!$authorizedUser) {
            return redirect()->route('dashboard')->with('error', __('messages.Unauthorized'));
        }
        if (!CheckAuthorizedDelete($authorizedUser->user_id, GetModuleID('question_management'))) {
            return response(__('messages.Unauthorized'), 403);
        }

        $question = DB::table('tbl_question')->where('question_id', $id)->first();
        if (!$question) {
            return redirect()->back()->with('error', __('messages.nodata'));
        }

        try {
            DB::table('tbl_question')->where('question_id', $id)->update([
                'status' => 0, // Set status to inactive
                'delete' => 1, // Mark as deleted
                'updated_on' => now(),
            ]);

            // Soft delete all related questions
            Questionrelated::where('question_id', $id)
                ->where('delete', 0)
                ->update([
                    'delete' => 1,
                    'updated_on' => now(),
                ]);

            return redirect()->route('admin.question.index')->with('success', __('question.question_deleted_successfully',['title' => $question->title,]));

        } catch (\Illuminate\Database\QueryException $e) {
            Log::error("Error deleting question: " . $e->getMessage());
            return redirect()->back()->with('error', __('messages.api_error'));
        }
    }
}
