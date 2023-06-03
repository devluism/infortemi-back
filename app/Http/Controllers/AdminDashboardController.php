<?php

namespace App\Http\Controllers;

use App\Http\Resources\OrderCollection;
use App\Models\Order;
use App\Models\Project;
use App\Models\Ticket;
use App\Repositories\OrderRepository;
use App\Repositories\TicketRepository;
use Illuminate\Http\Request;

class AdminDashboardController extends Controller
{
	protected $ticketRepository;
	protected $orderRepository;

	public function __construct(TicketRepository $ticketRepository, OrderRepository $orderRepository)
	{
		$this->ticketRepository = $ticketRepository;
		$this->orderRepository = $orderRepository;
	}

    public function getLast10SupportTickets()
	{
		$tickets = $this->ticketRepository->getTicketsByQuantity(10);

		$data = [];

		foreach($tickets as $ticket)
		{
			$data[] = [
				'id' => $ticket->id,
				'user_id' => $ticket->user_id, 
        		'status' => $ticket->status, 
				'subject' => $ticket->subject, 
				'categories' => $ticket->categories,
				'user' => [
					'name' => ucwords(strtolower($ticket->user->name)),
					'last_name' => ucwords(strtolower($ticket->user->last_name)),
					'user_name' => strtolower($ticket->user->user_name),
					'email' => strtolower($ticket->user->email),
					'profile_picture' => $ticket->user->profile_picture,
				]
			];
		}

		return response()->json($data,200);
	}
	public function getTicketsAdmin()
    {
        $tickets = Ticket::where('status', 0)->with('user')->with('messages')->orderBy('updated_at', 'desc')->take(10)->get();
        return response()->json($tickets, 200);
    }

	public function mostRequestedPackages()
	{
		$evaluations = Order::whereHas('packageMembership', function($q){
			$q->where('type', 1);
		})->count();


		$fast = Order::whereHas('packageMembership', function($q){
			$q->where('type', 2);
		})->count();

		$accelerated = Order::whereHas('packageMembership', function($q){
			$q->where('type', 2);
		})->count();

		$one_hundred = $evaluations + $fast + $accelerated;

		$data = [
			'evaluations' => $this->calculatePercent($one_hundred, $evaluations),
			'fast' => $this->calculatePercent($one_hundred, $fast),
			'accelerated' => $this->calculatePercent($one_hundred, $accelerated)
		];

		return response()->json($data, 200);
	}

	private function calculatePercent(int $one_hundred, int $amount)
	{
		return ($amount * 100) / $one_hundred;
	}

	public function getLast10Orders()
	{
		$orders = $this->orderRepository->getOrdersByQuantity(10);
		
		$data = [];
		foreach($orders as $order)
		{
			if (isset($order->project)) {
				$phase = ($order->project->phase2 == null && $order->project->phase1 == null)
				? ''
				: (($order->project->phase2 == null)
				? 'Phase 1'
				: 'Phase 2');
			}

			$data[] = [
				'id' => $order->id,
				'date' => $order->created_at,
				'status' => $order->status,
				'program' => [
					'name' => $order->packageMembership->getTypeName(),
					'account' => $order->packageMembership->account,
					'phase' => $phase ?? '',
				],
				'user' => [
					'id' => $order->user_id,
					'name' => ucwords(strtolower($order->user->name)),
					'last_name' => ucwords(strtolower($order->user->last_name)),
					'user_name' => strtolower($order->user->user_name),
					'email' => strtolower($order->user->email),
					'profile_picture' => $order->user->profile_picture,
				]
			];
		}
		return response()->json($data, 200);
	}

	public function getOrders()
	{
		$orders = $this->orderRepository->getOrders();
		
		foreach($orders as $order)
		{
			if (isset($order->project)) {
				$phase = ($order->project->phase2 == null && $order->project->phase1 == null)
				? ''
				: (($order->project->phase2 == null)
				? 'Phase 1'
				: 'Phase 2');
			}

			$data[] = [
				'id' => $order->id,
                'user_id' => $order->user->id,
                'user_name' => strtolower(explode(" ", $order->user->name)[0]." ".explode(" ", $order->user->last_name)[0]),
                'user_email' => strtolower($order->user->email),
                'program' => $order->packageMembership->getTypeName(),
                'phase' => $phase ?? "",
                'account' => $order->packageMembership->account,
                'status' => $order->status,
                'hash_id' => $order->hash,
                'amount' => $order->amount,
                'date' => $order->created_at->format('Y-m-d')
			];
		}
		return response()->json($data, 200);
	}
}
