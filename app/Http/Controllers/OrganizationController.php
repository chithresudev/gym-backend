<?php

namespace App\Http\Controllers;

use App\Models\Organization;
use Illuminate\Http\Request;

class OrganizationController extends Controller
{
    /**
     * Display a listing of the organizations.
     */
    public function index()
    {
        $organizations = Organization::all();
        return response()->json($organizations, 200);
    }

    /**
     * Show the form for creating a new organization.
     */
    public function create(Request $request)
    {
        // Validate request
        $request->validate([
            'organization_name' => 'required|unique:organizations,organization_name|string|max:255',
            'website' => 'nullable|max:255',
            'description' => 'nullable|string|max:1000',
            'logo' => 'nullable|image|max:2048',
'address' => 'nullable|string|max:255',
        ]);

        $organization = Organization::create([
            'organization_name' => $request->input('organization_name'),
            'website' => $request->input('website'),
            'description' => $request->input('description'),
            'address' => $request->input('address'),
        ]);
        if ($request->hasFile('logo')) {
            $logoPath = $request->file('logo')->store('organizations', 'public');
            $organization->logo = $logoPath;
            $organization->save();
        }

        return response()->json([
            'message' => 'Organization created successfully'
        ], 201);
        // Logic to show the form for creating a new gym
    }

    /**
     * Update the specified organization in storage.
     */
    public function searchOrganization(Request $request, $organization_name)
    {

        return $organization = Organization::where('organization_name', 'like', '%' . $organization_name . '%')->first();

        if ($organization->isEmpty()) {
            return response()->json(['message' => 'Organization not found'], 404);
        }

        return response()->json($organization, 200);
    }

    /**
     * Store a newly created organization in storage.
     */
    public function store(Request $request)
    {
        // Logic to validate and store a new organization
    }

    /**
     * Display the specified organization.
     */
    public function show($id)
    {
        // Logic to retrieve and return a specific organization by ID
    }

    /**
     * Show the form for editing the specified organization.
     */
    public function edit($id)
    {
        // Logic to show the form for editing a specific organization
    }

    /**
     * Update the specified organization in storage.
     */
    public function update(Request $request, $id)
    {
        // Logic to validate and update an existing organization
    }

    /**
     * Remove the specified organization from storage.
     */
    public function destroy($id)
    {
        // Logic to delete an organization
    }
}
