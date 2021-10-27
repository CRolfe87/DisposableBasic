<?php

namespace Modules\DisposableBasic\Http\Controllers;

use App\Contracts\Controller;
use App\Models\Airline;
use App\Models\Pirep;
use App\Models\Subfleet;
use App\Models\User;
use App\Models\Enums\PirepState;
use Modules\DisposableBasic\Services\DB_StatServices;
use League\ISO3166\ISO3166;

class DB_AirlineController extends Controller
{
    // Airlines
    public function index()
    {
        $airlines = Airline::where('active', 1)->orderby('name')->get();

        if (!$airlines) {
            flash()->error('No active airline found !');
            return redirect(route('frontend.dashboard.index'));
        }

        if ($airlines->count() === 1) {
            $airline = $airlines->first();
            return redirect(route('DBasic.airline', [$airline->icao]));
        }

        return view('DBasic::airlines.index', [
            'airlines' => $airlines,
            'country'  => new ISO3166(),
        ]);
    }

  // Airline Details
  public function show($icao)
  {
        $airline = Airline::with('aircraft.subfleet', 'journal')->where('icao', $icao)->first();

        if (!$airline) {
            flash()->error('Airline not found !');
            return redirect(route('DBasic.airlines'));
        }

        if ($airline) {
            $user_where = [];
            $user_where['airline_id'] = $airline->id;

            if (setting('pilots.hide_inactive')) {
                $user_where['state'] = 1;
            }

            $eager_users = array('rank', 'current_airport', 'home_airport');
            $users = User::with($eager_users)->where($user_where)->orderby('id')->get();

            $pirep_where = [];
            $pirep_where['airline_id'] = $airline->id;
            $pirep_where[] = ['state', '!=', PirepState::IN_PROGRESS];

            $eager_pireps = array('aircraft.subfleet', 'airline', 'dpt_airport', 'arr_airport', 'user');
            $pireps = Pirep::with($eager_pireps)->where('airline_id', $airline->id)->where($pirep_where)->orderby('submitted_at', 'desc')->paginate(25);

            $StatSvc = app(DB_StatServices::class);

            $finance = $StatSvc->AirlineFinance($airline->journal->id);
            $stats_basic = $StatSvc->BasicStats($airline->id);
            $stats_pirep = $StatSvc->PirepStats($airline->id);

            $eager_subfleets = array('aircraft', 'airline');
            $subfleets = Subfleet::with($eager_subfleets)->where('airline_id', $airline->id)->orderby('name')->get();

            return view('DBasic::airlines.show', [
                'airline'   => $airline,
                'country'   => new ISO3166(),
                'finance'   => $finance,
                'pireps'    => $pireps,
                'stats_b'   => $stats_basic,
                'stats_p'   => $stats_pirep,
                'subfleets' => $subfleets,
                'units'     => array('fuel' => setting('units.fuel'), 'weight' => setting('units.weight')),
                'users'     => $users,
            ]);
        }
    }
}
