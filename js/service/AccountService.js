export function fetchAll () {
	return new Promise((res, rej) => {
		setTimeout(() => {
			res([
				{
					id: 1,
					name: 'user@work.tld',
					bullet: '#eea941',
				}, {
					id: 2,
					bullet: '#4948ee',
					name: 'user.name@private.tld',
				}
			])
		}, 800);
	})
}

export function fetch () {
	return new Promise((res, rej) => {
		setTimeout(() => {
			res({
				id: 3,
			})
		}, 800);
	})
}
