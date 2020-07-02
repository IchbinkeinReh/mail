<!--
  - @copyright 2020 Patrick Bender <patrick@bender-it-services.de>
  -
  - @license GNU AGPL version 3 or any later version
  -
  - This program is free software: you can redistribute it and/or modify
  - it under the terms of the GNU Affero General Public License as
  - published by the Free Software Foundation, either version 3 of the
  - License, or (at your option) any later version.
  -
  - This program is distributed in the hope that it will be useful,
  - but WITHOUT ANY WARRANTY; without even the implied warranty of
  - MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
  - GNU Affero General Public License for more details.
  -
  - You should have received a copy of the GNU Affero General Public License
  - along with this program.  If not, see <http://www.gnu.org/licenses/>.
  -->

<template>
	<div id="aliases" class="section">
		<h2>{{ t('mail', 'Aliases') }}</h2>
		<div id="aliases-list">
			<table class="grid">
				<tbody>
					<tr v-for="curAlias in aliases" :key="curAlias.id">
						<td>
							{{ curAlias.alias }}
						</td>
						<td>
							{{ curAlias.name }}
						</td>
						<td>
							<button
								class="icon-delete"
								@click="deleteAlias(alias)"
							></button>
						</td>
					</tr>
				</tbody>
			</table>
		</div>
		<input
			id="alias"
			v-model="alias.alias"
			type="email"
			:placeholder="t('mail', 'Mail Address')"
			:disabled="loading"
		/>
		<input
			id="alias-name"
			v-model="alias.aliasName"
			type="text"
			:placeholder="t('mail', 'Name')"
			:disabled="loading"
		/>
		<button
			class="primary"
			:class="loading ? 'icon-loading-small-dark' : 'icon-checkmark-white'"
			:disabled="loading"
			@click="saveAlias"
		>
			{{ t('mail', 'Save') }}
		</button>
	</div>
</template>

<script>
import logger from '../logger'

export default {
	name: 'AliasSettings',
	props: {
		account: {
			type: Object,
			required: true,
		},
	},
	data() {
		return {
			loading: false,
			aliases: this.account.aliases,
			alias: {aliasName: '', alias: ''}
		}
	},
	methods: {
		deleteAlias(alias) {
			this.loading = true

			this.$store
				.dispatch('deleteAlias', {account: this.account, aliasToDelete: alias})
				.then(() => {
					logger.info('alias deleted')
					this.loading = false
				})
				.catch((error) => {
					logger.error('could not delete alias', {error})
					throw error
				})
		},
		saveAlias() {
			this.loading = true

			this.$store
				.dispatch('createAlias', {account: this.account, aliasToAdd: this.alias})
				.then(() => {
					logger.info('alias added')
					this.alias = {aliasName: '', alias: ''}
					this.loading = false
				})
				.catch((error) => {
					logger.error('could not add alias', {error})
					throw error
				})
		},
	},
}
</script>

<style lang="scss" scoped>
.primary {
	display: block;
	padding-left: 26px;
	background-position: 6px;

	&:after {
		left: 14px;
	}
}
input {
	width: 195px;
}
table {
	margin-bottom: 0.5rem;
}
td {
	padding:0px 5px 0px 5px;
}
</style>
