<?php

use KodeInfo\UserManagement\UserManagement;
use \KodeInfo\Forms\Rules\CustomerAddValidator;

class DepartmentsController extends BaseController
{

    protected $customerAddValidator;

    function __construct(CustomerAddValidator $customerAddValidator)
    {
        $this->customerAddValidator = $customerAddValidator;

        $this->beforeFilter('has_permission:departments.create', array('only' => array('create','store')));
        $this->beforeFilter('has_permission:departments.edit', array('only' => array('edit','update')));
        $this->beforeFilter('has_permission:departments.view', array('only' => array('all')));
        $this->beforeFilter('has_permission:departments.delete', array('only' => array('delete')));

    }

    public function create()
    {

        $this->data["admins"] = [];

        if(\KodeInfo\Utilities\Utils::isDepartmentAdmin(Auth::user()->id)){

            $department_admin = DepartmentAdmins::where('user_id',Auth::user()->id)->first();
            $department = Department::where('id',$department_admin->department_id)->first();
            $company = Company::where('id',$department->company_id)->first();

            $this->data['company'] = $company;

            $this->data["admins"] = DepartmentAdmins::getFreeDepartmentAdmins($company->id);

        }elseif (\KodeInfo\Utilities\Utils::isOperator(Auth::user()->id)) {

            $department_admin = OperatorsDepartment::where('user_id',Auth::user()->id)->first();
            $department = Department::where('id',$department_admin->department_id)->first();
            $company = Company::where('id',$department->company_id)->first();

            $this->data['company'] = $company;

            $this->data["admins"] = DepartmentAdmins::getFreeDepartmentAdmins($company->id);

        }else {

            $this->data['companies'] = Company::all();

            if(sizeof($this->data['companies'])>0){
                $this->data["admins"] = DepartmentAdmins::getFreeDepartmentAdmins($this->data['companies'][0]->id);
            }

        }

        $this->data['permissions'] = Permissions::all();

        return View::make('departments.create', $this->data);
    }

    public function edit($id)
    {

        try {
            $this->data['department'] = Department::findOrFail($id);
            $this->data['department']->permissions = explode(",", $this->data['department']->permissions);
            $this->data['department']->company = Company::find($this->data['department']->company_id);

            if (sizeof(DepartmentAdmins::where("department_id", $this->data['department']->id)->get()) > 0)
                $this->data['department_admin'] = DepartmentAdmins::where('department_id', $this->data['department']->id)->first();

            if (!empty($this->data['department_admin']))
                $this->data["admins"] = DepartmentAdmins::getFreeDepartmentAdmins($this->data['department']->company_id,[$this->data['department_admin']->user_id]);
            else
                $this->data["admins"] = DepartmentAdmins::getFreeDepartmentAdmins($this->data['department']->company_id);

            $this->data['permissions'] = Permissions::all();
            $this->data['companies'] = Company::all();
            return View::make('departments.edit', $this->data);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            Session::flash("error_msg", "Department not found");
            return Redirect::to("/departments/all");
        }
    }

    public function store()
    {

        if (Input::has("name") && Input::has("permissions")) {

            $department = new Department();
            $department->name = Input::get("name");
            $department->company_id = Input::get("company");
            $department->permissions = Input::has('permissions') ? implode(",", Input::get('permissions')) : "";
            $department->save();

            if (Input::get('department_admin') > 0) {
                $department_admin = new DepartmentAdmins();
                $department_admin->user_id = Input::get('department_admin');
                $department_admin->department_id = $department->id;
                $department_admin->save();
            }

            Session::flash("success_msg", "Department created successfully");
            return Redirect::to("/departments/all");

        } else {
            Session::flash("error_msg", "All fields required");
            return Redirect::to("/departments/create")->withInput();
        }
    }

    public function update()
    {

        if (!Input::has("id") || !Input::has('name') || !Input::has('permissions')) {
            Session::flash("error_msg", "All fields are required");
            return Redirect::to('/departments/update/' . Input::get("id"))->withInput();
        }

        try {

            if (Department::where('name', Input::get('name'))->where('id', '!=', Input::get('id'))->count() > 0) {
                Session::flash("error_msg", "Department name already exist");
                return Redirect::to('/departments/update/' . Input::get("id"))->withInput();
            }

            $department = Department::findOrFail(Input::get('id'));
            $department->name = Input::get('name');
            $department->company_id = Input::get('company');
            $department->permissions = Input::has('permissions') ? implode(",", Input::get('permissions')) : "";
            $department->save();

            //Remove old ids from department admin
            DepartmentAdmins::where('department_id', $department->id)->delete();

            if (Input::get('department_admin') > 0) {
                $department_admin = new DepartmentAdmins();
                $department_admin->user_id = Input::get('department_admin');
                $department_admin->department_id = $department->id;
                $department_admin->save();
            }

            Session::flash("success_msg", "Department updated successfully");
            return Redirect::to("/departments/all");

        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            Session::flash("error_msg", "Department not found");
            return Redirect::to("/departments/all");
        }
    }

    public function get($department_id)
    {
        $department = Department::findOrFail($department_id);
        $department->permissions_all = \Permissions::all();
        $department->permission_keys = explode(',', $department->permissions);
        return Response::json($department);
    }

    public function delete($department_id)
    {

        try {
            Department::findOrFail($department_id)->delete();
            Session::flash('success_msg', "Department deleted successfully");
            return Redirect::to('/departments/all');
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            Session::flash("error_msg", "Department not found");
            return Redirect::to("/departments/all");
        }

    }

    public function all()
    {
        $this->data['departments'] = Department::all();
        $this->data['permissions'] = Permissions::all();

        foreach ($this->data['departments'] as $department) {
            $department->company = Company::find($department->company_id);

            $department_admin = DepartmentAdmins::where("department_id", $department->id)->first();

            if (!empty($department_admin)) {
                $department->admin = User::find($department_admin->user_id);
            }
        }

        return View::make('departments.all', $this->data);
    }


}