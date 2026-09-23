<div class="mb-2">
    <label class="form-label">Nom du site</label>
    <input type="text" name="name" class="form-control" value="{{ $site->name ?? '' }}" required>
</div>
<div class="mb-2">
    <label class="form-label">Adresse</label>
    <input type="text" name="address" class="form-control" value="{{ $site->address ?? '' }}">
</div>
<div class="row">
    <div class="col-6 mb-2">
        <label class="form-label">Latitude</label>
        <input type="text" name="latitude" class="form-control" value="{{ $site->latitude ?? '' }}" required>
    </div>
    <div class="col-6 mb-2">
        <label class="form-label">Longitude</label>
        <input type="text" name="longitude" class="form-control" value="{{ $site->longitude ?? '' }}" required>
    </div>
</div>
<div class="mb-2">
    <label class="form-label">Rayon autorisé (mètres)</label>
    <input type="number" name="radius_m" class="form-control" value="{{ $site->radius_m ?? 100 }}" min="10" max="5000" required>
</div>
