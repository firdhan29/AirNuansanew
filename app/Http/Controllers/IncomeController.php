<?php

namespace App\Http\Controllers;

use App\Models\Income;
use Illuminate\Http\Request;

class IncomeController extends Controller
{
    public function store(Request $request) {
        Income::create($request->validate([
            'description' => 'required', 'amount' => 'required', 'date' => 'required'
        ]));
        return back();
    }

    public function update(Request $request, $id) {
        Income::find($id)->update($request->all());
        return back();
    }

    public function destroy($id) {
        Income::find($id)->delete();
        return back();
    }
}