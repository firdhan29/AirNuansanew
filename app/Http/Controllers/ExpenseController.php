<?php

namespace App\Http\Controllers;

use App\Models\Expense;
use Illuminate\Http\Request;

class ExpenseController extends Controller
{
    public function store(Request $request)
    {
        $request->validate([
            'description' => 'required',
            'amount' => 'required|numeric',
            'date' => 'required|date',
        ]);

        Expense::create($request->all());
        return back();
    }

    public function update(Request $request, $id)
    {
        $expense = Expense::findOrFail($id);
        $expense->update($request->all());
        return back();
    }

    public function destroy($id)
    {
        Expense::findOrFail($id)->delete();
        return back();
    }
}