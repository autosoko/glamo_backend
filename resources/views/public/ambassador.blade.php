@extends('public.layout')

@section('title', 'Ambasador wa Glamo - Glamo')

@section('content')
<section class="section ambassadorHero" aria-label="Jisajili kama ambasador wa Glamo">
  <div class="container ambassadorHero__grid">
    <div class="ambassadorHero__content">
      <span class="ambassadorBadge">Glamo Ambassador</span>
      <h1 class="ambassadorHero__title">Jisajili kama ambasador wa Glamo.</h1>
      <p class="ambassadorHero__subtitle">Jaza taarifa zako hapa chini. Timu yetu itakupigia simu.</p>
    </div>

    <div class="ambassadorHero__card">
      <form method="POST" action="{{ route('ambassador.store') }}" class="ambassadorForm">
        @csrf

        <div class="ambassadorField">
          <label for="ambFullName">Jina kamili</label>
          <input id="ambFullName" name="full_name" type="text" value="{{ old('full_name', auth()->user()->name ?? '') }}" required>
          @error('full_name')<div class="ambassadorErr">{{ $message }}</div>@enderror
        </div>

        <div class="ambassadorField">
          <label for="ambPhone">Namba ya simu</label>
          <input id="ambPhone" name="phone" type="text" value="{{ old('phone', auth()->user()->phone ?? '') }}" required>
          @error('phone')<div class="ambassadorErr">{{ $message }}</div>@enderror
        </div>

        <div class="ambassadorField">
          <label for="ambCity">Mji</label>
          <input id="ambCity" name="city" type="text" value="{{ old('city') }}" required>
          @error('city')<div class="ambassadorErr">{{ $message }}</div>@enderror
        </div>

        <div class="ambassadorField">
          <label for="ambEmail">Email (hiari)</label>
          <input id="ambEmail" name="email" type="email" value="{{ old('email', auth()->user()->email ?? '') }}">
          @error('email')<div class="ambassadorErr">{{ $message }}</div>@enderror
        </div>

        <div class="ambassadorField">
          <label for="ambNotes">Maelezo mafupi (hiari)</label>
          <textarea id="ambNotes" name="notes" rows="3">{{ old('notes') }}</textarea>
          @error('notes')<div class="ambassadorErr">{{ $message }}</div>@enderror
        </div>

        <button class="btn btn--primary" type="submit">Tuma taarifa</button>
      </form>
    </div>
  </div>
</section>
@endsection
