<div class="row">
    <div class="col-6 mb-2">
        <label class="form-label">Prénom</label>
        <input type="text" name="first_name" class="form-control" value="{{ $employee->first_name ?? '' }}" required>
    </div>
    <div class="col-6 mb-2">
        <label class="form-label">Nom</label>
        <input type="text" name="last_name" class="form-control" value="{{ $employee->last_name ?? '' }}" required>
    </div>
</div>
<div class="row">
    <div class="col-6 mb-2">
        <label class="form-label">Matricule</label>
        <input type="text" name="matricule" class="form-control" value="{{ $employee->matricule ?? '' }}" required>
    </div>
    <div class="col-6 mb-2">
        <label class="form-label">Téléphone</label>
        <input type="text" name="phone" class="form-control" value="{{ $employee->phone ?? '' }}">
    </div>
</div>

@unless($editing)
<div class="mb-2">
    <label class="form-label">Email (identifiant de connexion)</label>
    <input type="email" name="email" class="form-control" required>
</div>
@endunless

<div class="row">
    <div class="col-6 mb-2">
        <label class="form-label">Service</label>
        <select name="department_id" class="form-select">
            <option value="">—</option>
            @foreach($departments as $department)
                <option value="{{ $department->id }}" @selected(($employee->department_id ?? null) == $department->id)>
                    {{ $department->name }}
                </option>
            @endforeach
        </select>
    </div>
    <div class="col-6 mb-2">
        <label class="form-label">Site assigné</label>
        <select name="site_id" class="form-select">
            <option value="">—</option>
            @foreach($sites as $site)
                <option value="{{ $site->id }}" @selected(($employee->site_id ?? null) == $site->id)>
                    {{ $site->name }}
                </option>
            @endforeach
        </select>
    </div>
</div>

<div class="row">
    <div class="col-6 mb-2">
        <label class="form-label">Rôle</label>
        <select name="role" class="form-select" required>
            @php $currentRole = $employee->user->role ?? 'employe'; @endphp
            <option value="employe" @selected($currentRole === 'employe')>Employé</option>
            <option value="responsable" @selected($currentRole === 'responsable')>Responsable</option>
            <option value="rh_admin" @selected($currentRole === 'rh_admin')>RH / Admin</option>
            <option value="super_admin" @selected($currentRole === 'super_admin')>Super Admin</option>
        </select>
    </div>
    <div class="col-6 mb-2">
        <label class="form-label">Statut</label>
        <select name="status" class="form-select" required>
            <option value="active" @selected(($employee->status ?? 'active') === 'active')>Actif</option>
            <option value="inactive" @selected(($employee->status ?? '') === 'inactive')>Inactif</option>
        </select>
    </div>
</div>
