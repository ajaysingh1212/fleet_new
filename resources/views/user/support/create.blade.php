@extends('layouts.app')

@section('page-eyebrow', 'Workspace')
@section('page-title', 'New Support Ticket')

@section('breadcrumb')
    <li class="breadcrumb-item"><a href="{{ route('support.index') }}">Support</a></li>
@endsection

@section('content')
    <div class="card tracker-surface-card">
        <div class="card-body">
            <form method="POST" action="{{ route('support.store') }}">
                @csrf
                <div class="row g-3">
                    <div class="col-md-8">
                        <label class="tracker-form-label" for="subject">Subject</label>
                        <input id="subject" name="subject" type="text" class="form-control @error('subject') is-invalid @enderror" value="{{ old('subject') }}" required>
                        @error('subject')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <div class="col-md-4">
                        <label class="tracker-form-label" for="priority">Priority</label>
                        <select id="priority" name="priority" class="form-select @error('priority') is-invalid @enderror">
                            @foreach (['low', 'medium', 'high', 'urgent'] as $priority)
                                <option value="{{ $priority }}" @selected(old('priority', 'medium') === $priority)>{{ ucfirst($priority) }}</option>
                            @endforeach
                        </select>
                        @error('priority')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <div class="col-12">
                        <label class="tracker-form-label" for="message">Message</label>
                        <textarea id="message" name="message" rows="5" class="form-control @error('message') is-invalid @enderror" required>{{ old('message') }}</textarea>
                        @error('message')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                </div>
                <div class="mt-4 d-flex gap-2">
                    <button type="submit" class="btn tracker-primary-btn">Submit Ticket</button>
                    <a href="{{ route('support.index') }}" class="btn tracker-outline-btn">Cancel</a>
                </div>
            </form>
        </div>
    </div>
@endsection
