import { evaluateDisplayRules, normalizeDisplayRules } from './display-rules';

type ScheduleFacts = {
	siteWeekday: number;
	siteMinuteOfDay: number;
};

type ScheduleEvaluator = (
	config: ReturnType< typeof normalizeDisplayRules >,
	facts: ScheduleFacts
) => ReturnType< typeof evaluateDisplayRules >;

const evaluateForSchedule =
	evaluateDisplayRules as unknown as ScheduleEvaluator;

describe( 'display rule site-time schedule gates', () => {
	test( 'normalizes bounded site schedule values', () => {
		const rules = normalizeDisplayRules( {
			visibility: {
				schedule: {
					timezone: 'site',
					days: [ 1, 3, 3, 9 ],
					start: '09:00',
					end: '17:30',
				},
			},
		} ) as unknown as {
			visibility: Record< string, unknown >;
		};

		expect( rules.visibility.schedule ).toEqual( {
			timezone: 'site',
			days: [ 1, 3 ],
			start: '09:00',
			end: '17:30',
		} );
	} );

	test( 'uses inclusive same-day boundaries and rejects outside minutes or days', () => {
		const rules = normalizeDisplayRules( {
			visibility: {
				schedule: {
					timezone: 'site',
					days: [ 1 ],
					start: '09:00',
					end: '17:00',
				},
			},
		} );

		expect(
			evaluateForSchedule( rules, {
				siteWeekday: 1,
				siteMinuteOfDay: 9 * 60,
			} )
		).toEqual( expect.objectContaining( { visible: true } ) );
		expect(
			evaluateForSchedule( rules, {
				siteWeekday: 1,
				siteMinuteOfDay: 17 * 60,
			} )
		).toEqual( expect.objectContaining( { visible: true } ) );
		expect(
			evaluateForSchedule( rules, {
				siteWeekday: 1,
				siteMinuteOfDay: 17 * 60 + 1,
			} )
		).toEqual(
			expect.objectContaining( {
				visible: false,
				reasons: [ 'schedule_mismatch' ],
			} )
		);
		expect(
			evaluateForSchedule( rules, {
				siteWeekday: 2,
				siteMinuteOfDay: 12 * 60,
			} )
		).toEqual(
			expect.objectContaining( {
				visible: false,
				reasons: [ 'schedule_mismatch' ],
			} )
		);
	} );

	test( 'treats an overnight window as starting on a configured day', () => {
		const rules = normalizeDisplayRules( {
			visibility: {
				schedule: {
					timezone: 'site',
					days: [ 1 ],
					start: '22:00',
					end: '02:00',
				},
			},
		} );

		expect(
			evaluateForSchedule( rules, {
				siteWeekday: 1,
				siteMinuteOfDay: 23 * 60,
			} )
		).toEqual( expect.objectContaining( { visible: true } ) );
		expect(
			evaluateForSchedule( rules, {
				siteWeekday: 2,
				siteMinuteOfDay: 60,
			} )
		).toEqual( expect.objectContaining( { visible: true } ) );
		expect(
			evaluateForSchedule( rules, {
				siteWeekday: 2,
				siteMinuteOfDay: 2 * 60 + 1,
			} )
		).toEqual(
			expect.objectContaining( {
				visible: false,
				reasons: [ 'schedule_mismatch' ],
			} )
		);
	} );
} );
